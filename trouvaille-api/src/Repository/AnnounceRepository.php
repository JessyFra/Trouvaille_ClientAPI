<?php

namespace App\Repository;

use App\Entity\Announce;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AnnounceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Announce::class);
    }

    /**
     * @param int|null    $cityId
     * @param int|null    $categoryId
     * @param string|null $type
     * @param string|null $search
     * @param string|null $authorName  — filtre par username (exact)
     * @param int         $page
     * @param int         $limit
     * @param bool        $allStatuses — si true, inclut les annonces clôturées
     */
    public function findWithFilters(
        ?int    $cityId,
        ?int    $categoryId,
        ?string $type,
        ?string $search,
        ?string $authorName  = null,
        int     $page        = 1,
        int     $limit       = 12,
        bool    $allStatuses = false,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.city', 'c')
            ->leftJoin('a.author', 'u')
            ->leftJoin('a.images', 'i', 'WITH', 'i.isMain = true')
            ->leftJoin('a.categories', 'cat')
            ->addSelect('c', 'u', 'i', 'cat')
            ->orderBy('a.createdAt', 'DESC');

        // On accumule les conditions dans un tableau puis on les applique
        // pour éviter tout conflit entre where() et andWhere() dans Doctrine.
        $conditions = [];
        $params     = [];

        if (!$allStatuses) {
            $conditions[] = 'a.status = :status';
            $params['status'] = 'open';
        }

        if ($cityId) {
            $conditions[] = 'c.id = :cityId';
            $params['cityId'] = $cityId;
        }

        if ($categoryId) {
            $conditions[] = 'cat.id = :categoryId';
            $params['categoryId'] = $categoryId;
        }

        if ($type && in_array($type, ['offer', 'request'], true)) {
            $conditions[] = 'a.type = :type';
            $params['type'] = $type;
        }

        if ($search && $search !== '') {
            $conditions[] = '(a.title LIKE :search OR a.description LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($authorName && $authorName !== '') {
            $conditions[] = 'u.name = :authorName';
            $params['authorName'] = $authorName;
        }

        foreach ($conditions as $i => $condition) {
            if ($i === 0) {
                $qb->where($condition);
            } else {
                $qb->andWhere($condition);
            }
        }

        foreach ($params as $key => $value) {
            $qb->setParameter($key, $value);
        }

        $total = (clone $qb)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $announces = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['data' => $announces, 'total' => (int) $total];
    }
}

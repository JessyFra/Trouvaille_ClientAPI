<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function findUserMessages(int $userId): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.author', 'a')
            ->leftJoin('m.recipient', 'r')
            ->addSelect('a', 'r')
            ->where('a.id = :userId OR r.id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findThread(int $userId, int $otherId): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.author', 'a')
            ->leftJoin('m.recipient', 'r')
            ->addSelect('a', 'r')
            ->where('(a.id = :userId AND r.id = :otherId) OR (a.id = :otherId AND r.id = :userId)')
            ->setParameter('userId', $userId)
            ->setParameter('otherId', $otherId)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

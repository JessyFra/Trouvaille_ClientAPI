<?php

namespace App\Controller;

use App\Entity\Announce;
use App\Entity\AnnounceImage;
use App\Repository\AnnounceRepository;
use App\Repository\CityRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/announces')]
#[OA\Tag(name: 'Annonces')]
class AnnounceController extends AbstractController
{
    // -------------------------------------------------------
    // GET /api/announces
    // -------------------------------------------------------
    #[Route('', name: 'announces_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/announces',
        summary: 'Liste les annonces ouvertes avec filtres optionnels',
        security: [],
        parameters: [
            new OA\Parameter(name: 'author',      in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'city_id',     in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type',        in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['offer', 'request'])),
            new OA\Parameter(name: 'search',      in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page',        in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit',       in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 12)),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des annonces')]
    )]
    public function list(Request $request, AnnounceRepository $repo): JsonResponse
    {
        $page       = max(1, (int) $request->query->get('page', 1));
        $limit      = min(50, max(1, (int) $request->query->get('limit', 12)));
        $cityId     = $request->query->get('city_id')     ? (int) $request->query->get('city_id')     : null;
        $catId      = $request->query->get('category_id') ? (int) $request->query->get('category_id') : null;
        $type       = $request->query->get('type');
        $search     = $request->query->get('search');
        $authorName = $request->query->get('author') ?: null;

        $result = $repo->findWithFilters(
            cityId: $cityId,
            categoryId: $catId,
            type: $type,
            search: $search,
            authorName: $authorName,
            page: $page,
            limit: $limit,
        );

        // ← Ce return était manquant, causant une réponse null côté client
        return $this->json([
            'data'  => array_map(fn(Announce $a) => $this->serialize($a, $request), $result['data']),
            'total' => $result['total'],
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    // -------------------------------------------------------
    // GET /api/announces/{id}
    // -------------------------------------------------------
    #[Route('/{id}', name: 'announces_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/announces/{id}',
        summary: 'Détail d\'une annonce',
        security: [],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Annonce retournée'),
            new OA\Response(response: 404, description: 'Annonce introuvable'),
        ]
    )]
    public function show(int $id, AnnounceRepository $repo, Request $request): JsonResponse
    {
        $announce = $repo->find($id);

        if (!$announce) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'Annonce introuvable.']],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json($this->serialize($announce, $request, true));
    }

    // -------------------------------------------------------
    // POST /api/announces
    // -------------------------------------------------------
    #[Route('', name: 'announces_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/announces',
        summary: 'Créer une annonce (authentifié)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'type', 'city_id', 'category_ids'],
                properties: [
                    new OA\Property(property: 'title',        type: 'string',  example: 'Vélo de ville à vendre'),
                    new OA\Property(property: 'description',  type: 'string',  example: 'Très bon état, peu utilisé.'),
                    new OA\Property(property: 'price',        type: 'number',  example: 80),
                    new OA\Property(property: 'type',         type: 'string',  enum: ['offer', 'request']),
                    new OA\Property(property: 'city_id',      type: 'integer', example: 1),
                    new OA\Property(property: 'category_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 3]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Annonce créée'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        CityRepository $cityRepo,
        CategoryRepository $categoryRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $title = trim($data['title'] ?? '');
        if (strlen($title) < 3 || strlen($title) > 64) {
            return $this->json(
                ['error' => ['code' => 'INVALID_TITLE', 'message' => 'Le titre doit faire entre 3 et 64 caractères.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $type = $data['type'] ?? '';
        if (!in_array($type, ['offer', 'request'])) {
            return $this->json(
                ['error' => ['code' => 'INVALID_TYPE', 'message' => 'Le type doit être "offer" ou "request".']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $city = $cityRepo->find($data['city_id'] ?? 0);
        if (!$city) {
            return $this->json(
                ['error' => ['code' => 'INVALID_CITY', 'message' => 'Ville introuvable.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $categoryIds = $data['category_ids'] ?? [];
        if (empty($categoryIds)) {
            return $this->json(
                ['error' => ['code' => 'INVALID_CATEGORIES', 'message' => 'Au moins une catégorie est requise.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $announce = new Announce();
        $announce->setTitle($title);
        $announce->setDescription($data['description'] ?? null);
        $announce->setPrice(max(0, (float) ($data['price'] ?? 0)));
        $announce->setType($type);
        $announce->setCity($city);
        $announce->setAuthor($this->getUser());

        foreach ($categoryIds as $catId) {
            $category = $categoryRepo->find($catId);
            if ($category) {
                $announce->addCategory($category);
            }
        }

        $em->persist($announce);
        $em->flush();

        return $this->json($this->serialize($announce, $request, true), Response::HTTP_CREATED);
    }

    // -------------------------------------------------------
    // PATCH /api/announces/{id}
    // -------------------------------------------------------
    #[Route('/{id}', name: 'announces_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Patch(
        path: '/api/announces/{id}',
        summary: 'Modifier une annonce (propriétaire ou admin)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title',       type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'price',       type: 'number'),
                    new OA\Property(property: 'city_id',     type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Annonce modifiée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Annonce introuvable'),
        ]
    )]
    public function update(
        int $id,
        Request $request,
        AnnounceRepository $repo,
        CityRepository $cityRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $announce = $repo->find($id);
        if (!$announce) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'Annonce introuvable.']],
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$this->canManage($announce)) {
            return $this->json(
                ['error' => ['code' => 'FORBIDDEN', 'message' => 'Vous ne pouvez pas modifier cette annonce.']],
                Response::HTTP_FORBIDDEN
            );
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) {
            $title = trim($data['title']);
            if (strlen($title) >= 3 && strlen($title) <= 64) {
                $announce->setTitle($title);
            }
        }
        if (array_key_exists('description', $data)) {
            $announce->setDescription($data['description']);
        }
        if (isset($data['price'])) {
            $announce->setPrice(max(0, (float) $data['price']));
        }
        if (isset($data['city_id'])) {
            $city = $cityRepo->find($data['city_id']);
            if ($city) $announce->setCity($city);
        }

        $em->flush();

        return $this->json($this->serialize($announce, $request, true));
    }

    // -------------------------------------------------------
    // PATCH /api/announces/{id}/close
    // -------------------------------------------------------
    #[Route('/{id}/close', name: 'announces_close', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Patch(
        path: '/api/announces/{id}/close',
        summary: 'Clôturer une annonce (propriétaire ou admin)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Annonce clôturée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Annonce introuvable'),
        ]
    )]
    public function close(int $id, AnnounceRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $announce = $repo->find($id);
        if (!$announce) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'Annonce introuvable.']],
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$this->canManage($announce)) {
            return $this->json(
                ['error' => ['code' => 'FORBIDDEN', 'message' => 'Vous ne pouvez pas clôturer cette annonce.']],
                Response::HTTP_FORBIDDEN
            );
        }

        $announce->setStatus('closed');
        $em->flush();

        return $this->json(['message' => 'Annonce clôturée.']);
    }

    // -------------------------------------------------------
    // PATCH /api/announces/{id}/reopen
    // -------------------------------------------------------
    #[Route('/{id}/reopen', name: 'announces_reopen', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Patch(
        path: '/api/announces/{id}/reopen',
        summary: 'Réouvrir une annonce (propriétaire ou admin)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Annonce réouverte'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Annonce introuvable'),
        ]
    )]
    public function reopen(int $id, AnnounceRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $announce = $repo->find($id);
        if (!$announce) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'Annonce introuvable.']],
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$this->canManage($announce)) {
            return $this->json(
                ['error' => ['code' => 'FORBIDDEN', 'message' => 'Vous ne pouvez pas réouvrir cette annonce.']],
                Response::HTTP_FORBIDDEN
            );
        }

        $announce->setStatus('open');
        $em->flush();

        return $this->json(['message' => 'Annonce réouverte.']);
    }

    // -------------------------------------------------------
    // DELETE /api/announces/{id}
    // -------------------------------------------------------
    #[Route('/{id}', name: 'announces_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(
        path: '/api/announces/{id}',
        summary: 'Supprimer une annonce (propriétaire ou admin)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Annonce supprimée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Annonce introuvable'),
        ]
    )]
    public function delete(int $id, AnnounceRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $announce = $repo->find($id);
        if (!$announce) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'Annonce introuvable.']],
                Response::HTTP_NOT_FOUND
            );
        }

        if (!$this->canManage($announce)) {
            return $this->json(
                ['error' => ['code' => 'FORBIDDEN', 'message' => 'Vous ne pouvez pas supprimer cette annonce.']],
                Response::HTTP_FORBIDDEN
            );
        }

        $em->remove($announce);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // -------------------------------------------------------
    // Helpers privés
    // -------------------------------------------------------
    private function canManage(Announce $announce): bool
    {
        $user = $this->getUser();
        return $announce->getAuthor()->getId() === $user->getId()
            || in_array('ROLE_ADMIN', $user->getRoles());
    }

    private function serialize(Announce $a, Request $request, bool $full = false): array
    {
        $baseUrl = $request->getSchemeAndHttpHost();

        $mainImage      = null;
        $mainImageThumb = null;
        foreach ($a->getImages() as $img) {
            if ($img->isMain()) {
                $mainImage      = $baseUrl . '/' . $img->getImagePath();
                $mainImageThumb = $img->getThumbnailPath()
                    ? $baseUrl . '/' . $img->getThumbnailPath()
                    : $mainImage;
                break;
            }
        }

        $data = [
            'id'          => $a->getId(),
            'title'       => $a->getTitle(),
            'description' => $a->getDescription(),
            'price'       => $a->getPrice(),
            'status'      => $a->getStatus(),
            'type'        => $a->getType(),
            'created_at'  => $a->getCreatedAt()->format('Y-m-d H:i:s'),
            'main_image'       => $mainImage,
            'main_image_thumb' => $mainImageThumb,
            'city'        => ['id' => $a->getCity()->getId(), 'name' => $a->getCity()->getName()],
            'author'      => [
                'id'           => $a->getAuthor()->getId(),
                'name'         => $a->getAuthor()->getName(),
                'display_name' => $a->getAuthor()->getDisplayName(),
            ],
            'categories'  => array_map(
                fn($c) => ['id' => $c->getId(), 'name' => $c->getName()],
                $a->getCategories()->toArray()
            ),
        ];

        if ($full) {
            $data['images'] = array_map(fn($img) => [
                'id'      => $img->getId(),
                'url'     => $baseUrl . '/uploads/announces/' . basename($img->getImagePath()),
                'is_main' => $img->isMain(),
            ], $a->getImages()->toArray());
        }

        return $data;
    }
}

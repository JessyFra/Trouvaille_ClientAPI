<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\AnnounceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/users')]
#[OA\Tag(name: 'Utilisateurs')]
class UserController extends AbstractController
{
    // -------------------------------------------------------
    // GET /api/users/{username}
    // Profil public d'un utilisateur
    // -------------------------------------------------------
    #[Route('/{username}', name: 'users_public_profile', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users/{username}',
        summary: 'Retourne le profil public d\'un utilisateur et ses annonces ouvertes',
        security: [],
        parameters: [
            new OA\Parameter(name: 'username', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page',     in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit',    in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 12)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Profil public retourné'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
        ]
    )]
    public function publicProfile(
        string $username,
        Request $request,
        UserRepository $userRepo,
        AnnounceRepository $announceRepo
    ): JsonResponse {
        $user = $userRepo->findOneBy(['name' => $username]);

        if (!$user) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'Utilisateur introuvable.']],
                Response::HTTP_NOT_FOUND
            );
        }

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 12)));

        $result = $announceRepo->findWithFilters(
            cityId: null,
            categoryId: null,
            type: null,
            search: null,
            authorName: $username,
            page: $page,
            limit: $limit,
        );

        $announces = array_map(function ($a) use ($request) {
            $baseUrl   = $request->getSchemeAndHttpHost();
            $mainImage = null;
            foreach ($a->getImages() as $img) {
                if ($img->isMain()) {
                    $mainImage = $baseUrl . '/' . $img->getImagePath();
                    break;
                }
            }

            return [
                'id'         => $a->getId(),
                'title'      => $a->getTitle(),
                'price'      => $a->getPrice(),
                'type'       => $a->getType(),
                'status'     => $a->getStatus(),
                'created_at' => $a->getCreatedAt()->format('Y-m-d H:i:s'),
                'main_image' => $mainImage,
                'city'       => ['id' => $a->getCity()->getId(), 'name' => $a->getCity()->getName()],
                'categories' => array_map(
                    fn($c) => ['id' => $c->getId(), 'name' => $c->getName()],
                    $a->getCategories()->toArray()
                ),
            ];
        }, $result['data']);

        return $this->json([
            'user' => [
                'id'           => $user->getId(),
                'name'         => $user->getName(),
                'display_name' => $user->getDisplayName(),
                'biography'    => $user->getBiography(),
                'created_at'   => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            ],
            'announces' => [
                'data'  => $announces,
                'total' => $result['total'],
                'page'  => $page,
                'limit' => $limit,
            ],
        ]);
    }
}

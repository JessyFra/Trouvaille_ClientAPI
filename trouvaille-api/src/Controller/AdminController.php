<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\AnnounceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/admin')]
#[OA\Tag(name: 'Administration')]
class AdminController extends AbstractController
{
    // -------------------------------------------------------
    // Vérifie que l'utilisateur est bien admin
    // -------------------------------------------------------
    private function requireAdmin(): ?JsonResponse
    {
        if (!in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
            return $this->json(
                ['error' => ['code' => 'FORBIDDEN', 'message' => 'Accès réservé aux administrateurs.']],
                Response::HTTP_FORBIDDEN
            );
        }
        return null;
    }

    // -------------------------------------------------------
    // GET /api/admin/users
    // Liste tous les utilisateurs
    // -------------------------------------------------------
    #[Route('/users', name: 'admin_users_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/users',
        summary: 'Liste tous les utilisateurs (admin)',
        parameters: [
            new OA\Parameter(name: 'page',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des utilisateurs'),
            new OA\Response(response: 403, description: 'Accès refusé'),
        ]
    )]
    public function listUsers(Request $request, UserRepository $repo): JsonResponse
    {
        if ($error = $this->requireAdmin()) return $error;

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

        $users = $repo->findBy([], ['createdAt' => 'DESC'], $limit, ($page - 1) * $limit);
        $total = $repo->count([]);

        return $this->json([
            'data'  => array_map(fn(User $u) => [
                'id'           => $u->getId(),
                'name'         => $u->getName(),
                'display_name' => $u->getDisplayName(),
                'role'         => $u->getRole(),
                'banned'       => $u->isBanned(),
                'created_at'   => $u->getCreatedAt()->format('Y-m-d H:i:s'),
            ], $users),
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    // -------------------------------------------------------
    // PATCH /api/admin/users/{id}/ban
    // Bannir un utilisateur
    // -------------------------------------------------------
    #[Route('/users/{id}/ban', name: 'admin_user_ban', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Patch(
        path: '/api/admin/users/{id}/ban',
        summary: 'Bannir un utilisateur (admin)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur banni'),
            new OA\Response(response: 400, description: 'Impossible de bannir un admin'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
        ]
    )]
    public function ban(int $id, UserRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        if ($error = $this->requireAdmin()) return $error;

        $user = $repo->find($id);
        if (!$user) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'Utilisateur introuvable.']],
                Response::HTTP_NOT_FOUND
            );
        }

        if ($user->getRole() === 'admin') {
            return $this->json(
                ['error' => ['code' => 'FORBIDDEN', 'message' => 'Impossible de bannir un administrateur.']],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($user->getId() === $this->getUser()->getId()) {
            return $this->json(
                ['error' => ['code' => 'FORBIDDEN', 'message' => 'Vous ne pouvez pas vous bannir vous-même.']],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user->setBanned(true);
        $em->flush();

        return $this->json(['message' => "Utilisateur {$user->getName()} banni."]);
    }

    // -------------------------------------------------------
    // PATCH /api/admin/users/{id}/unban
    // Débannir un utilisateur
    // -------------------------------------------------------
    #[Route('/users/{id}/unban', name: 'admin_user_unban', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Patch(
        path: '/api/admin/users/{id}/unban',
        summary: 'Débannir un utilisateur (admin)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur débanni'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
        ]
    )]
    public function unban(int $id, UserRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        if ($error = $this->requireAdmin()) return $error;

        $user = $repo->find($id);
        if (!$user) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'Utilisateur introuvable.']],
                Response::HTTP_NOT_FOUND
            );
        }

        $user->setBanned(false);
        $em->flush();

        return $this->json(['message' => "Utilisateur {$user->getName()} débanni."]);
    }

    // -------------------------------------------------------
    // GET /api/admin/announces
    // Liste toutes les annonces (y compris clôturées)
    // -------------------------------------------------------
    #[Route('/announces', name: 'admin_announces_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/announces',
        summary: 'Liste toutes les annonces sans filtre de statut (admin)',
        parameters: [
            new OA\Parameter(name: 'page',  in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des annonces'),
            new OA\Response(response: 403, description: 'Accès refusé'),
        ]
    )]
    public function listAnnounces(Request $request, AnnounceRepository $repo): JsonResponse
    {
        if ($error = $this->requireAdmin()) return $error;

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

        $announces = $repo->findBy([], ['createdAt' => 'DESC'], $limit, ($page - 1) * $limit);
        $total     = $repo->count([]);

        return $this->json([
            'data'  => array_map(fn($a) => [
                'id'         => $a->getId(),
                'title'      => $a->getTitle(),
                'type'       => $a->getType(),
                'status'     => $a->getStatus(),
                'price'      => $a->getPrice(),
                'created_at' => $a->getCreatedAt()->format('Y-m-d H:i:s'),
                'city'       => ['id' => $a->getCity()->getId(), 'name' => $a->getCity()->getName()],
                'author'     => ['id' => $a->getAuthor()->getId(), 'name' => $a->getAuthor()->getName()],
            ], $announces),
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    // -------------------------------------------------------
    // PATCH /api/admin/announces/{id}/close
    // Clôturer n'importe quelle annonce
    // -------------------------------------------------------
    #[Route('/announces/{id}/close', name: 'admin_announce_close', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Patch(
        path: '/api/admin/announces/{id}/close',
        summary: 'Clôturer n\'importe quelle annonce (admin)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Annonce clôturée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Annonce introuvable'),
        ]
    )]
    public function closeAnnounce(int $id, AnnounceRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        if ($error = $this->requireAdmin()) return $error;

        $announce = $repo->find($id);
        if (!$announce) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'Annonce introuvable.']],
                Response::HTTP_NOT_FOUND
            );
        }

        $announce->setStatus('closed');
        $em->flush();

        return $this->json(['message' => 'Annonce clôturée.']);
    }

    // -------------------------------------------------------
    // DELETE /api/admin/announces/{id}
    // Supprimer n'importe quelle annonce
    // -------------------------------------------------------
    #[Route('/announces/{id}', name: 'admin_announce_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(
        path: '/api/admin/announces/{id}',
        summary: 'Supprimer n\'importe quelle annonce (admin)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Annonce supprimée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Annonce introuvable'),
        ]
    )]
    public function deleteAnnounce(int $id, AnnounceRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        if ($error = $this->requireAdmin()) return $error;

        $announce = $repo->find($id);
        if (!$announce) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'Annonce introuvable.']],
                Response::HTTP_NOT_FOUND
            );
        }

        $em->remove($announce);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

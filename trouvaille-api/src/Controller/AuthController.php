<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/auth')]
#[OA\Tag(name: 'Auth')]
class AuthController extends AbstractController
{
    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Créer un nouveau compte',
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'password'],
                properties: [
                    new OA\Property(property: 'name',     type: 'string', example: 'jean_dupont'),
                    new OA\Property(property: 'password', type: 'string', example: 'MonMotDePasse1!'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Compte créé avec succès'),
            new OA\Response(response: 409, description: 'Nom d\'utilisateur déjà pris'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validation
        $name     = trim($data['name'] ?? '');
        $password = $data['password'] ?? '';

        if (strlen($name) < 3 || strlen($name) > 16) {
            return $this->json(
                ['error' => ['code' => 'INVALID_NAME', 'message' => 'Le nom doit faire entre 3 et 16 caractères.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            return $this->json(
                ['error' => ['code' => 'INVALID_NAME', 'message' => 'Le nom ne peut contenir que des lettres, chiffres et underscores.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (strlen($password) < 6) {
            return $this->json(
                ['error' => ['code' => 'INVALID_PASSWORD', 'message' => 'Le mot de passe doit faire au moins 6 caractères.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Unicité du nom
        $existing = $em->getRepository(User::class)->findOneBy(['name' => $name]);
        if ($existing) {
            return $this->json(
                ['error' => ['code' => 'NAME_TAKEN', 'message' => 'Ce nom d\'utilisateur est déjà pris.']],
                Response::HTTP_CONFLICT
            );
        }

        // Création
        $user = new User();
        $user->setName($name);
        $user->setPassword($hasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        return $this->json([
            'message' => 'Compte créé avec succès.',
            'user' => [
                'id'         => $user->getId(),
                'name'       => $user->getName(),
                'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/me', name: 'auth_me', methods: ['GET'])]
    #[OA\Get(
        path: '/api/auth/me',
        summary: 'Retourne le profil de l\'utilisateur connecté',
        responses: [
            new OA\Response(response: 200, description: 'Profil retourné'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id'           => $user->getId(),
            'name'         => $user->getName(),
            'display_name' => $user->getDisplayName(),
            'biography'    => $user->getBiography(),
            'role'         => $user->getRole(),
            'banned'       => $user->isBanned(),
            'created_at'   => $user->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    // -------------------------------------------------------
    // PATCH /api/auth/profile
    // Mettre à jour display_name, biography et/ou mot de passe
    // -------------------------------------------------------
    #[Route('/profile', name: 'auth_profile_update', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/auth/profile',
        summary: 'Mettre à jour le profil de l\'utilisateur connecté',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'display_name',    type: 'string',  nullable: true, example: 'Marie D.'),
                    new OA\Property(property: 'biography',       type: 'string',  nullable: true, example: 'Passionnée de déco.'),
                    new OA\Property(property: 'current_password', type: 'string',  nullable: true, example: 'ancienMdp'),
                    new OA\Property(property: 'new_password',    type: 'string',  nullable: true, example: 'nouveauMdp'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profil mis à jour'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        // ── display_name ──
        if (array_key_exists('display_name', $data)) {
            $displayName = $data['display_name'] !== null
                ? trim((string) $data['display_name'])
                : null;

            if ($displayName !== null && strlen($displayName) > 16) {
                return $this->json(
                    ['error' => ['code' => 'INVALID_DISPLAY_NAME', 'message' => 'Le nom affiché ne peut pas dépasser 16 caractères.']],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $user->setDisplayName($displayName ?: null);
        }

        // ── biography ──
        if (array_key_exists('biography', $data)) {
            $biography = $data['biography'] !== null
                ? trim((string) $data['biography'])
                : null;

            if ($biography !== null && strlen($biography) > 1000) {
                return $this->json(
                    ['error' => ['code' => 'INVALID_BIOGRAPHY', 'message' => 'La biographie ne peut pas dépasser 1000 caractères.']],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $user->setBiography($biography ?: null);
        }

        // ── Changement de mot de passe ──
        if (!empty($data['new_password'])) {
            $currentPassword = $data['current_password'] ?? '';
            $newPassword     = $data['new_password'];

            if (!$hasher->isPasswordValid($user, $currentPassword)) {
                return $this->json(
                    ['error' => ['code' => 'WRONG_PASSWORD', 'message' => 'Mot de passe actuel incorrect.']],
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (strlen($newPassword) < 6) {
                return $this->json(
                    ['error' => ['code' => 'INVALID_PASSWORD', 'message' => 'Le nouveau mot de passe doit faire au moins 6 caractères.']],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $user->setPassword($hasher->hashPassword($user, $newPassword));
        }

        $em->flush();

        return $this->json([
            'id'           => $user->getId(),
            'name'         => $user->getName(),
            'display_name' => $user->getDisplayName(),
            'biography'    => $user->getBiography(),
            'role'         => $user->getRole(),
            'banned'       => $user->isBanned(),
            'created_at'   => $user->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }
}

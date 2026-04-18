<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/messages')]
#[OA\Tag(name: 'Messagerie')]
class MessageController extends AbstractController
{
    // -------------------------------------------------------
    // GET /api/messages/conversations
    // Liste toutes les conversations de l'utilisateur connecté
    // -------------------------------------------------------
    #[Route('/conversations', name: 'messages_conversations', methods: ['GET'])]
    #[OA\Get(
        path: '/api/messages/conversations',
        summary: 'Liste les conversations de l\'utilisateur connecté',
        responses: [
            new OA\Response(response: 200, description: 'Liste des conversations'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function conversations(MessageRepository $repo): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        // Récupère tous les messages où je suis auteur ou destinataire
        $messages = $repo->findUserMessages($me->getId());

        // Regroupe par interlocuteur
        $conversations = [];
        foreach ($messages as $message) {
            $other = $message->getAuthor()->getId() === $me->getId()
                ? $message->getRecipient()
                : $message->getAuthor();

            $otherId = $other->getId();

            if (!isset($conversations[$otherId])) {
                $conversations[$otherId] = [
                    'interlocutor' => [
                        'id'           => $other->getId(),
                        'name'         => $other->getName(),
                        'display_name' => $other->getDisplayName(),
                    ],
                    'last_message' => [
                        'content'    => $message->getContent(),
                        'created_at' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
                        'is_mine'    => $message->getAuthor()->getId() === $me->getId(),
                    ],
                    'unread_count' => 0,
                ];
            }
        }

        return $this->json([
            'data'  => array_values($conversations),
            'total' => count($conversations),
        ]);
    }

    // -------------------------------------------------------
    // GET /api/messages/conversations/{userId}
    // Récupère tous les messages échangés avec un utilisateur
    // -------------------------------------------------------
    #[Route('/conversations/{userId}', name: 'messages_thread', methods: ['GET'], requirements: ['userId' => '\d+'])]
    #[OA\Get(
        path: '/api/messages/conversations/{userId}',
        summary: 'Messages échangés avec un utilisateur',
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Messages retournés'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
        ]
    )]
    public function thread(
        int $userId,
        MessageRepository $repo,
        UserRepository $userRepo
    ): JsonResponse {
        /** @var User $me */
        $me = $this->getUser();

        $other = $userRepo->find($userId);
        if (!$other) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'Utilisateur introuvable.']],
                Response::HTTP_NOT_FOUND
            );
        }

        if ($other->getId() === $me->getId()) {
            return $this->json(
                ['error' => ['code' => 'INVALID', 'message' => 'Vous ne pouvez pas vous écrire à vous-même.']],
                Response::HTTP_BAD_REQUEST
            );
        }

        $messages = $repo->findThread($me->getId(), $other->getId());

        return $this->json([
            'interlocutor' => [
                'id'           => $other->getId(),
                'name'         => $other->getName(),
                'display_name' => $other->getDisplayName(),
            ],
            'data' => array_map(fn(Message $m) => [
                'id'         => $m->getId(),
                'content'    => $m->getContent(),
                'created_at' => $m->getCreatedAt()->format('Y-m-d H:i:s'),
                'is_mine'    => $m->getAuthor()->getId() === $me->getId(),
            ], $messages),
            'total' => count($messages),
        ]);
    }

    // -------------------------------------------------------
    // POST /api/messages/{userId}
    // Envoyer un message à un utilisateur
    // -------------------------------------------------------
    #[Route('/{userId}', name: 'messages_send', methods: ['POST'], requirements: ['userId' => '\d+'])]
    #[OA\Post(
        path: '/api/messages/{userId}',
        summary: 'Envoyer un message à un utilisateur',
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['content'],
                properties: [
                    new OA\Property(property: 'content', type: 'string', example: 'Bonjour, votre annonce m\'intéresse !'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Message envoyé'),
            new OA\Response(response: 400, description: 'Contenu invalide'),
            new OA\Response(response: 403, description: 'Compte banni'),
            new OA\Response(response: 404, description: 'Destinataire introuvable'),
        ]
    )]
    public function send(
        int $userId,
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $me */
        $me = $this->getUser();

        // Un banni ne peut pas envoyer de message
        if ($me->isBanned()) {
            return $this->json(
                ['error' => ['code' => 'BANNED', 'message' => 'Votre compte est suspendu.']],
                Response::HTTP_FORBIDDEN
            );
        }

        $recipient = $userRepo->find($userId);
        if (!$recipient) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'Destinataire introuvable.']],
                Response::HTTP_NOT_FOUND
            );
        }

        if ($recipient->getId() === $me->getId()) {
            return $this->json(
                ['error' => ['code' => 'INVALID', 'message' => 'Vous ne pouvez pas vous écrire à vous-même.']],
                Response::HTTP_BAD_REQUEST
            );
        }

        $data    = json_decode($request->getContent(), true);
        $content = trim($data['content'] ?? '');

        if (strlen($content) < 1) {
            return $this->json(
                ['error' => ['code' => 'EMPTY_CONTENT', 'message' => 'Le message ne peut pas être vide.']],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (strlen($content) > 5000) {
            return $this->json(
                ['error' => ['code' => 'CONTENT_TOO_LONG', 'message' => 'Le message ne peut pas dépasser 5000 caractères.']],
                Response::HTTP_BAD_REQUEST
            );
        }

        $message = new Message();
        $message->setContent($content);
        $message->setAuthor($me);
        $message->setRecipient($recipient);

        $em->persist($message);
        $em->flush();

        return $this->json([
            'id'         => $message->getId(),
            'content'    => $message->getContent(),
            'created_at' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
            'recipient'  => [
                'id'   => $recipient->getId(),
                'name' => $recipient->getName(),
            ],
        ], Response::HTTP_CREATED);
    }
}

<?php

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/categories')]
#[OA\Tag(name: 'Catégories')]
class CategoryController extends AbstractController
{
    #[Route('', name: 'categories_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/categories',
        summary: 'Liste toutes les catégories',
        security: [],
        responses: [new OA\Response(response: 200, description: 'Liste des catégories')]
    )]
    public function list(CategoryRepository $repo): JsonResponse
    {
        $categories = $repo->findBy([], ['name' => 'ASC']);

        return $this->json([
            'data' => array_map(fn(Category $c) => [
                'id'   => $c->getId(),
                'name' => $c->getName(),
            ], $categories)
        ]);
    }
}

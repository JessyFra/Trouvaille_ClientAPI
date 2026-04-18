<?php

namespace App\Controller;

use App\Entity\City;
use App\Repository\CityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/cities')]
#[OA\Tag(name: 'Villes')]
class CityController extends AbstractController
{
    #[Route('', name: 'cities_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/cities',
        summary: 'Liste toutes les villes',
        security: [],
        responses: [new OA\Response(response: 200, description: 'Liste des villes')]
    )]
    public function list(CityRepository $repo): JsonResponse
    {
        $cities = $repo->findBy([], ['name' => 'ASC']);

        return $this->json([
            'data' => array_map(fn(City $c) => [
                'id'   => $c->getId(),
                'name' => $c->getName(),
            ], $cities)
        ]);
    }
}

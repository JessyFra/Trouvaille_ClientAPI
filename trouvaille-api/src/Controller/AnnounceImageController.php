<?php

namespace App\Controller;

use App\Entity\AnnounceImage;
use App\Repository\AnnounceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use OpenApi\Attributes as OA;

#[Route('/api/announces')]
#[OA\Tag(name: 'Annonces')]
class AnnounceImageController extends AbstractController
{
    private const THUMB_WIDTH  = 400;
    private const THUMB_HEIGHT = 300;

    // -------------------------------------------------------
    // POST /api/announces/{id}/images
    // -------------------------------------------------------
    #[Route('/{id}/images', name: 'announce_image_upload', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Post(
        path: '/api/announces/{id}/images',
        summary: 'Ajouter une image à une annonce (multipart/form-data)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'image',   type: 'string', format: 'binary'),
                        new OA\Property(property: 'is_main', type: 'boolean', example: false),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Image ajoutée'),
            new OA\Response(response: 400, description: 'Fichier invalide'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Annonce introuvable'),
        ]
    )]
    public function upload(
        int $id,
        Request $request,
        AnnounceRepository $repo,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): JsonResponse {
        $announce = $repo->find($id);
        if (!$announce) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'Annonce introuvable.']],
                Response::HTTP_NOT_FOUND
            );
        }

        $user = $this->getUser();
        if (
            $announce->getAuthor()->getId() !== $user->getId()
            && !in_array('ROLE_ADMIN', $user->getRoles())
        ) {
            return $this->json(
                ['error' => ['code' => 'FORBIDDEN', 'message' => 'Accès refusé.']],
                Response::HTTP_FORBIDDEN
            );
        }

        $file = $request->files->get('image');
        if (!$file) {
            return $this->json(
                ['error' => ['code' => 'NO_FILE', 'message' => 'Aucun fichier reçu.']],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Validation type MIME réel
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return $this->json(
                ['error' => ['code' => 'INVALID_FILE', 'message' => 'Seuls les formats JPG, PNG et WebP sont acceptés.']],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Taille max 5 Mo
        if ($file->getSize() > 5 * 1024 * 1024) {
            return $this->json(
                ['error' => ['code' => 'FILE_TOO_LARGE', 'message' => 'L\'image ne doit pas dépasser 5 Mo.']],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Nom de fichier sécurisé généré côté serveur
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName     = $slugger->slug($originalName);
        $uid          = uniqid();
        $extension    = $file->guessExtension();
        $newFilename  = $safeName . '-' . $uid . '.' . $extension;
        $thumbFilename = $safeName . '-' . $uid . '-thumb.jpg';

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/announces';
        $file->move($uploadDir, $newFilename);

        // Génération du thumbnail 400×300 (crop centré)
        $thumbGenerated = $this->generateThumbnail(
            $uploadDir . '/' . $newFilename,
            $uploadDir . '/' . $thumbFilename,
            self::THUMB_WIDTH,
            self::THUMB_HEIGHT
        );

        // Si is_main, on retire l'ancienne image principale
        $isMain = filter_var($request->request->get('is_main', false), FILTER_VALIDATE_BOOLEAN);
        if ($isMain) {
            foreach ($announce->getImages() as $existing) {
                if ($existing->isMain()) {
                    $existing->setIsMain(false);
                }
            }
        }

        // Si c'est la première image, elle devient principale automatiquement
        if ($announce->getImages()->isEmpty()) {
            $isMain = true;
        }

        $image = new AnnounceImage();
        $image->setAnnounce($announce);
        $image->setImagePath('uploads/announces/' . $newFilename);
        $image->setIsMain($isMain);

        if ($thumbGenerated) {
            $image->setThumbnailPath('uploads/announces/' . $thumbFilename);
        }

        $em->persist($image);
        $em->flush();

        $baseUrl = $request->getSchemeAndHttpHost();

        return $this->json([
            'id'            => $image->getId(),
            'url'           => $baseUrl . '/uploads/announces/' . $newFilename,
            'thumbnail_url' => $thumbGenerated ? $baseUrl . '/uploads/announces/' . $thumbFilename : null,
            'is_main'       => $image->isMain(),
        ], Response::HTTP_CREATED);
    }

    // -------------------------------------------------------
    // DELETE /api/announces/{id}/images/{imageId}
    // -------------------------------------------------------
    #[Route('/{id}/images/{imageId}', name: 'announce_image_delete', methods: ['DELETE'], requirements: ['id' => '\d+', 'imageId' => '\d+'])]
    #[OA\Delete(
        path: '/api/announces/{id}/images/{imageId}',
        summary: 'Supprimer une image d\'une annonce',
        parameters: [
            new OA\Parameter(name: 'id',      in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'imageId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Image supprimée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Image introuvable'),
        ]
    )]
    public function deleteImage(
        int $id,
        int $imageId,
        AnnounceRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $announce = $repo->find($id);
        if (!$announce) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'Annonce introuvable.']],
                Response::HTTP_NOT_FOUND
            );
        }

        $user = $this->getUser();
        if (
            $announce->getAuthor()->getId() !== $user->getId()
            && !in_array('ROLE_ADMIN', $user->getRoles())
        ) {
            return $this->json(
                ['error' => ['code' => 'FORBIDDEN', 'message' => 'Accès refusé.']],
                Response::HTTP_FORBIDDEN
            );
        }

        $image = null;
        foreach ($announce->getImages() as $img) {
            if ($img->getId() === $imageId) {
                $image = $img;
                break;
            }
        }

        if (!$image) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'Image introuvable.']],
                Response::HTTP_NOT_FOUND
            );
        }

        $publicDir = $this->getParameter('kernel.project_dir') . '/public/';

        // Suppression du fichier principal
        $filePath = $publicDir . $image->getImagePath();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Suppression du thumbnail s'il existe
        if ($image->getThumbnailPath()) {
            $thumbPath = $publicDir . $image->getThumbnailPath();
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
        }

        $em->remove($image);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // -------------------------------------------------------
    // Génération du thumbnail via GD (crop centré)
    // -------------------------------------------------------
    private function generateThumbnail(string $srcPath, string $destPath, int $w, int $h): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        $mime = mime_content_type($srcPath);

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($srcPath),
            'image/png'  => @imagecreatefrompng($srcPath),
            'image/webp' => @imagecreatefromwebp($srcPath),
            default      => false,
        };

        if (!$src) {
            return false;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        // Ratio crop centré (cover)
        $ratioSrc  = $srcW / $srcH;
        $ratioDest = $w / $h;

        if ($ratioSrc > $ratioDest) {
            // Image plus large → on crop sur les côtés
            $cropH = $srcH;
            $cropW = (int) round($srcH * $ratioDest);
            $cropX = (int) round(($srcW - $cropW) / 2);
            $cropY = 0;
        } else {
            // Image plus haute → on crop en haut/bas
            $cropW = $srcW;
            $cropH = (int) round($srcW / $ratioDest);
            $cropX = 0;
            $cropY = (int) round(($srcH - $cropH) / 2);
        }

        $thumb = imagecreatetruecolor($w, $h);
        imagecopyresampled($thumb, $src, 0, 0, $cropX, $cropY, $w, $h, $cropW, $cropH);

        $result = imagejpeg($thumb, $destPath, 82); // qualité 82% – bon compromis taille/qualité

        imagedestroy($src);
        imagedestroy($thumb);

        return $result;
    }
}

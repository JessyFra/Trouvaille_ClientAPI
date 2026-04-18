<?php

namespace App\Command;

use App\Repository\AnnounceImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:generate-missing-thumbnails',
    description: 'Génère les thumbnails manquants (thumbnail_path = NULL) pour les images existantes.',
)]
class GenerateMissingThumbnailsCommand extends Command
{
    private const THUMB_WIDTH  = 400;
    private const THUMB_HEIGHT = 300;

    public function __construct(
        private readonly AnnounceImageRepository $imageRepo,
        private readonly EntityManagerInterface  $em,
        private readonly ParameterBagInterface   $params,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans écrire sur le disque ni en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $publicDir = $this->params->get('kernel.project_dir') . '/public/';
        $uploadDir = $publicDir . 'uploads/announces/';

        // Récupère toutes les images sans thumbnail
        $images = $this->imageRepo->createQueryBuilder('i')
            ->where('i.thumbnailPath IS NULL')
            ->getQuery()
            ->getResult();

        if (empty($images)) {
            $io->success('Aucune image sans thumbnail. Rien à faire.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('%d image(s) sans thumbnail trouvée(s).', count($images)));
        if ($dryRun) {
            $io->warning('Mode dry-run : aucune modification ne sera effectuée.');
        }

        $ok = $skip = $fail = 0;

        foreach ($images as $image) {
            $imagePath = $publicDir . $image->getImagePath();

            if (!file_exists($imagePath)) {
                $io->warning(sprintf('Fichier introuvable, ignoré : %s', $image->getImagePath()));
                $skip++;
                continue;
            }

            // Déduit le nom du thumb à partir du nom de l'image originale
            $basename      = pathinfo(basename($image->getImagePath()), PATHINFO_FILENAME);
            $thumbFilename = $basename . '-thumb.jpg';
            $thumbPath     = $uploadDir . $thumbFilename;
            $thumbRelPath  = 'uploads/announces/' . $thumbFilename;

            // Si le fichier thumb existe déjà sur disque mais pas en base, on met juste à jour la BDD
            $generated = false;
            if (file_exists($thumbPath)) {
                $io->note(sprintf('Thumb déjà présent sur disque pour %s, mise à jour BDD uniquement.', basename($imagePath)));
                $generated = true;
            } elseif (!$dryRun) {
                $generated = $this->generateThumbnail(
                    $imagePath,
                    $thumbPath,
                    self::THUMB_WIDTH,
                    self::THUMB_HEIGHT
                );
            } else {
                // dry-run : on simule comme si ça marchait
                $generated = true;
            }

            if ($generated) {
                if (!$dryRun) {
                    $image->setThumbnailPath($thumbRelPath);
                }
                $io->writeln(sprintf(
                    '  <info>✓</info> %s → %s',
                    basename($imagePath),
                    $thumbFilename
                ));
                $ok++;
            } else {
                $io->error(sprintf('Échec de génération pour : %s', basename($imagePath)));
                $fail++;
            }
        }

        if (!$dryRun && $ok > 0) {
            $this->em->flush();
        }

        $io->newLine();
        $io->success(sprintf(
            'Terminé — %d générés, %d ignorés (fichier absent), %d échoués.',
            $ok, $skip, $fail
        ));

        return $fail > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    // -------------------------------------------------------
    // Génération du thumbnail via GD (crop centré) — identique
    // à AnnounceImageController::generateThumbnail()
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
            $cropH = $srcH;
            $cropW = (int) round($srcH * $ratioDest);
            $cropX = (int) round(($srcW - $cropW) / 2);
            $cropY = 0;
        } else {
            $cropW = $srcW;
            $cropH = (int) round($srcW / $ratioDest);
            $cropX = 0;
            $cropY = (int) round(($srcH - $cropH) / 2);
        }

        $thumb = imagecreatetruecolor($w, $h);
        imagecopyresampled($thumb, $src, 0, 0, $cropX, $cropY, $w, $h, $cropW, $cropH);

        $result = imagejpeg($thumb, $destPath, 82);

        imagedestroy($src);
        imagedestroy($thumb);

        return $result;
    }
}

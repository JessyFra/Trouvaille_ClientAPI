<?php

namespace App\Entity;

use App\Repository\AnnounceImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnnounceImageRepository::class)]
#[ORM\Table(name: 'announce_image')]
class AnnounceImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Announce::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'announce_id', nullable: false, onDelete: 'CASCADE')]
    private Announce $announce;

    #[ORM\Column(length: 255)]
    private string $imagePath;

    // Thumbnail 400×300 JPEG généré à l'upload pour les listes/cards
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnailPath = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isMain = false;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnnounce(): Announce
    {
        return $this->announce;
    }
    public function setAnnounce(Announce $announce): self
    {
        $this->announce = $announce;
        return $this;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }
    public function setImagePath(string $imagePath): self
    {
        $this->imagePath = $imagePath;
        return $this;
    }

    public function getThumbnailPath(): ?string
    {
        return $this->thumbnailPath;
    }
    public function setThumbnailPath(?string $thumbnailPath): self
    {
        $this->thumbnailPath = $thumbnailPath;
        return $this;
    }

    public function isMain(): bool
    {
        return $this->isMain;
    }
    public function setIsMain(bool $isMain): self
    {
        $this->isMain = $isMain;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}

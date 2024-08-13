<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Book\Isbn;

final class Book
{
    public function __construct(
        private Isbn $isbn,
        private string $title,
        private \DateTimeImmutable $birth,
        /** @var Topic[] */
        private array $topics,
    ) {}

    public function getIsbn(): Isbn
    {
        return $this->isbn;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getBirth(): \DateTimeImmutable
    {
        return $this->birth;
    }

    public function setBirth(\DateTimeImmutable $birth): void
    {
        $this->birth = $birth;
    }

    /**
     * @return Topic[]
     */
    public function getTopics(): array
    {
        return $this->topics;
    }

    public function addTopic(Topic $topic): void
    {
        $this->topics[] = $topic;
    }
}

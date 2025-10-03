<?php

declare(strict_types=1);

namespace JTL\News;

use DateTime;
use JTL\DB\DbInterface;
use JTL\MagicCompatibilityTrait;

/**
 * Class Comment
 * @package JTL\News
 */
class Comment implements CommentInterface
{
    use MagicCompatibilityTrait;

    /**
     * @var array<string, string>
     */
    protected static array $mapping = [
        'cKommentar'   => 'Text',
        'cName'        => 'Name',
        'dErstellt'    => 'DateCreatedCompat',
        'dErstellt_de' => 'DateCreatedCompat',
    ];

    private string $newsTitle = '';

    private int $id = 0;

    private int $newsID = 0;

    private int $customerID = 0;

    private bool $isActive = false;

    private string $name;

    private string $mail;

    private string $text;

    private int $isAdmin;

    private int $parentCommentID;

    /**
     * @var Comment[]
     */
    private array $childComments = [];

    private DateTime $dateCreated;

    private DbInterface $db;

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritdoc
     */
    public function load(int $id): CommentInterface
    {
        $this->id = $id;
        $comment  = $this->db->getObjects(
            'SELECT * 
                FROM tnewskommentar
                WHERE kNewsKommentar = :cid',
            ['cid' => $this->id]
        );
        if (\count($comment) === 0) {
            throw new \InvalidArgumentException('Provided link id ' . $this->id . ' not found.');
        }

        return $this->map($comment);
    }

    /**
     * @inheritdoc
     */
    public function loadByParentCommentID(int $parentID): ?CommentInterface
    {
        $this->id = $parentID;
        $comment  = $this->db->getObjects(
            'SELECT *
                FROM tnewskommentar
                WHERE parentCommentID = :cid',
            ['cid' => $this->id]
        );

        return \count($comment) > 0 ? $this->map($comment) : null;
    }

    /**
     * @inheritdoc
     */
    public function map(array $comments): CommentInterface
    {
        foreach ($comments as $comment) {
            $this->setID((int)$comment->kNewsKommentar);
            $this->setNewsID((int)$comment->kNews);
            $this->setCustomerID((int)$comment->kKunde);
            $this->setIsActive((int)$comment->nAktiv === 1);
            $this->setName($comment->cName);
            $this->setMail($comment->cEmail);
            $this->setText($comment->cKommentar);
            $this->setIsAdmin((int)$comment->isAdmin);
            $this->setParentCommentID((int)$comment->parentCommentID);
            $this->setDateCreated($comment->dErstellt);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getID(): int
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function setID(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @inheritdoc
     */
    public function getNewsID(): int
    {
        return $this->newsID;
    }

    /**
     * @inheritdoc
     */
    public function setNewsID(int $newsID): void
    {
        $this->newsID = $newsID;
    }

    /**
     * @inheritdoc
     */
    public function getCustomerID(): int
    {
        return $this->customerID;
    }

    /**
     * @inheritdoc
     */
    public function setCustomerID(int $customerID): void
    {
        $this->customerID = $customerID;
    }

    /**
     * @inheritdoc
     */
    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @inheritdoc
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @inheritdoc
     */
    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @inheritdoc
     */
    public function getMail(): string
    {
        return $this->mail;
    }

    /**
     * @inheritdoc
     */
    public function setMail(string $mail): void
    {
        $this->mail = $mail;
    }

    /**
     * @inheritdoc
     */
    public function getContent(): string
    {
        return $this->text;
    }

    /**
     * @inheritdoc
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @inheritdoc
     */
    public function setText(string $text): void
    {
        $this->text = $text;
    }

    /**
     * @inheritdoc
     */
    public function getDateCreated(): DateTime
    {
        return $this->dateCreated;
    }

    /**
     * @inheritdoc
     */
    public function setDateCreated(string $dateCreated): void
    {
        $this->dateCreated = new DateTime($dateCreated);
    }

    /**
     * @inheritdoc
     */
    public function getDateCreatedCompat(): string
    {
        return $this->dateCreated->format('Y-m-d H:i');
    }

    /**
     * @inheritdoc
     */
    public function getNewsTitle(): string
    {
        return $this->newsTitle;
    }

    /**
     * @inheritdoc
     */
    public function setNewsTitle(string $newsTitle): void
    {
        $this->newsTitle = $newsTitle;
    }

    /**
     * @inheritdoc
     */
    public function getIsAdmin(): int
    {
        return $this->isAdmin;
    }

    /**
     * @inheritdoc
     */
    public function setIsAdmin(int $isAdmin): void
    {
        $this->isAdmin = $isAdmin;
    }

    /**
     * @inheritdoc
     */
    public function getParentCommentID(): int
    {
        return $this->parentCommentID;
    }

    /**
     * @inheritdoc
     */
    public function setParentCommentID(int $parentCommentID): void
    {
        $this->parentCommentID = $parentCommentID;
    }

    /**
     * @inheritdoc
     */
    public function getChildComments(): array
    {
        return $this->childComments;
    }

    /**
     * @inheritdoc
     */
    public function setChildComments(array $childComments): void
    {
        $this->childComments = $childComments;
    }

    /**
     * @inheritdoc
     */
    public function setChildComment(Comment $childComment): void
    {
        $this->childComments[] = $childComment;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $res       = \get_object_vars($this);
        $res['db'] = '*truncated*';

        return $res;
    }
}

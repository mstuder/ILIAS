<?php declare(strict_types=1);
/* Copyright (c) 1998-2019 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * @author  Niels Theen <ntheen@databay.de>
 */
class ilMailValueObject
{
    private string $recipients;
    private string $recipientsCC;
    private string $recipientsBCC;
    private string $subject;
    private string $body;

    /** @var string[] */
    private array $attachments;
    private bool $usePlaceholders;
    private bool $saveInSentBox;
    private string $from;

    /**
     * @param string $from
     * @param string $recipients
     * @param string $recipientsCC
     * @param string $recipientsBCC
     * @param string $subject
     * @param string $body
     * @param string[] $attachments
     * @param bool $usePlaceholders
     * @param bool $saveInSentBox
     */
    public function __construct(
        string $from,
        string $recipients,
        string $recipientsCC,
        string $recipientsBCC,
        string $subject,
        string $body,
        array $attachments,
        bool $usePlaceholders = false,
        bool $saveInSentBox = false
    ) {
        $this->from = $from;
        $this->recipients = $recipients;
        $this->recipientsCC = $recipientsCC;
        $this->recipientsBCC = $recipientsBCC;
        $this->subject = $subject;
        $this->body = $body;
        $this->attachments = array_filter(array_map('trim', $attachments));
        $this->usePlaceholders = $usePlaceholders;
        $this->saveInSentBox = $saveInSentBox;
    }

    /**
     * @return string
     */
    public function getRecipients() : string
    {
        return $this->recipients;
    }

    /**
     * @return string
     */
    public function getRecipientsCC() : string
    {
        return $this->recipientsCC;
    }

    /**
     * @return string
     */
    public function getRecipientsBCC() : string
    {
        return $this->recipientsBCC;
    }

    /**
     * @return string
     */
    public function getSubject() : string
    {
        return $this->subject;
    }

    /**
     * @return string
     */
    public function getBody() : string
    {
        return $this->body;
    }

    /**
     * @return string[]
     */
    public function getAttachments() : array
    {
        return $this->attachments;
    }

    /**
     * @return bool
     */
    public function isUsingPlaceholders() : bool
    {
        return $this->usePlaceholders;
    }

    /**
     * @return bool
     */
    public function shouldSaveInSentBox() : bool
    {
        return $this->saveInSentBox;
    }

    /**
     * @return string
     */
    public function getFrom() : string
    {
        return $this->from;
    }
}

<?php

namespace TofuPlugin\Models;

use TofuPlugin\Helpers\Template;
use TofuPlugin\Structure\MailAddress;

class Mail
{
    /**
     * Recipients
     *
     * @var MailAddress[]
     */
    protected array $to = [];

    /**
     * CC Recipients
     *
     * @var MailAddress[]
     */
    protected array $cc = [];

    /**
     * BCC Recipients
     *
     * @var MailAddress[]
     */
    protected array $bcc = [];

    /**
     * Sender
     *
     * @var MailAddress
     */
    protected MailAddress $from;

    /**
     * Subject
     *
     * @var string
     */
    protected string $subject = '';

    /**
     * Body
     *
     * @var string
     */
    protected string $body = '';

    /**
     * Headers
     *
     * @var string[]
     */
    protected array $headers = [];

    /**
     * Attachments
     *
     * @var array<string, string>
     */
    protected array $attachments = [];

    /**
     * Mail constructor.
     */
    public function __construct()
    {
    }

    /**
     * Add a recipient.
     *
     * @param string|MailAddress $to
     * @return Mail
     */
    public function addTo($to): Mail
    {
        if (is_string($to)) {
            foreach (explode(',', $to) as $email) {
                $this->to[] = new MailAddress(trim($email));
            }
        } else {
            $this->to[] = $to;
        }

        return $this;
    }

    /**
     * Add a CC recipient.
     *
     * @param string|MailAddress $cc
     * @return Mail
     */
    public function addCc($cc): Mail
    {
        if (is_string($cc)) {
            foreach (explode(',', $cc) as $email) {
                $this->cc[] = new MailAddress(trim($email));
            }
        } else {
            $this->cc[] = $cc;
        }

        return $this;
    }

    /**
     * Add a BCC recipient.
     *
     * @param string|MailAddress $bcc
     * @return Mail
     */
    public function addBcc($bcc): Mail
    {
        if (is_string($bcc)) {
            foreach (explode(',', $bcc) as $email) {
                $this->bcc[] = new MailAddress(trim($email));
            }
        } else {
            $this->bcc[] = $bcc;
        }

        return $this;
    }

    /**
     * Set the sender.
     *
     * @param string|MailAddress $from
     * @return Mail
     */
    public function setFrom($from): Mail
    {
        if (is_string($from)) {
            $from = new MailAddress(trim($from));
        }
        $this->from = $from;
        return $this;
    }

    /**
     * Set the subject.
     *
     * @param string $subject
     * @return Mail
     */
    public function setSubject(string $subject): Mail
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set the subject from a template.
     *
     * @param string $templatePath
     * @return Mail
     */
    public function setSubjectFromTemplate(string $templatePath): Mail
    {
        $subject = Template::getTemplateContent($templatePath);
        if (empty($subject)) {
            throw new \RuntimeException('Mail subject template is empty: ' . esc_html($templatePath));
        }
        $this->setSubject($subject);
        return $this;
    }

    /**
     * Set the body.
     *
     * @param string $body
     * @return Mail
     */
    public function setBody(string $body): Mail
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Set the body from a template.
     *
     * @param string $templatePath
     * @return Mail
     */
    public function setBodyFromTemplate(string $templatePath): Mail
    {
        $body = Template::getTemplateContent($templatePath);
        if (empty($body)) {
            throw new \RuntimeException('Mail body template is empty: ' . esc_html($templatePath));
        }
        $this->setBody($body);
        return $this;
    }

    /**
     * Add a header.
     *
     * @param string $header
     * @return Mail
     */
    public function addHeader(string $header): Mail
    {
        $this->headers[] = $header;
        return $this;
    }

    /**
     * Add an attachment.
     *
     * @param string $fileName
     * @param string $filePath
     * @return Mail
     */
    public function addAttachment(string $fileName, string $filePath): Mail
    {
        $this->attachments[$fileName] = $filePath;
        return $this;
    }

    /**
     * Send the email.
     *
     * @return bool
     */
    public function send(): bool
    {
        $toEmails = array_map(fn($addr) => (string)$addr, $this->to);
        $ccEmails = array_map(fn($addr) => (string)$addr, $this->cc);
        $bccEmails = array_map(fn($addr) => (string)$addr, $this->bcc);
        $headers = $this->headers;

        if (!empty($ccEmails)) {
            $headers[] = 'Cc: ' . implode(', ', $ccEmails);
        }
        if (!empty($bccEmails)) {
            $headers[] = 'Bcc: ' . implode(', ', $bccEmails);
        }
        if (isset($this->from)) {
            $headers[] = 'From: ' . (string)$this->from;
        }

        return \wp_mail(
            $toEmails,
            $this->subject,
            $this->body,
            $headers,
            $this->attachments
        );
    }

    public function toArray(): array
    {
        return [
            'to' => array_map(fn($addr) => (string)$addr, $this->to),
            'cc' => array_map(fn($addr) => (string)$addr, $this->cc),
            'bcc' => array_map(fn($addr) => (string)$addr, $this->bcc),
            'from' => isset($this->from) ? (string)$this->from : null,
            'subject' => $this->subject,
            'body' => $this->body,
            'headers' => $this->headers,
            'attachments' => $this->attachments,
        ];
    }
}

<?php

namespace AppBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;


class FlashMessage
{

    /**
     * @var int
     */
    private $id;

    /**
     * @Assert\NotBlank()
     * @Assert\Length(max=160, min=30)
     * @var string
     */
    private $content;

    /**
     * @Assert\NotBlank()
     * @var string
     */
    private $color = "#d9ad0f";

    /**
     * @Assert\NotBlank()
     * @var string
     */
    private $orientation = "ltr";

    /**
     * @var \DateTime
     */
    private $updated;

    /**
     * @Assert\NotBlank()
     * @Assert\GreaterThan("now")
     * @var \DateTime
     */
    private $expire;

    /**
     * @var Mosque
     */
    private $mosque;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param string|null $content
     * @return FlashMessage
     */
    public function setContent($content): FlashMessage
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * @param \DateTime $updated
     * @return FlashMessage
     */
    public function setUpdated($updated): FlashMessage
    {
        $this->updated = $updated;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getExpire()
    {
        return $this->expire;
    }

    /**
     * @param \DateTime $expire
     * @return FlashMessage
     */
    public function setExpire(\DateTime $expire): FlashMessage
    {
        $this->expire = $expire;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isExpired()
    {
        return $this->expire < new \DateTime();
//        $expire = clone  $this->expire;
//        $timezoneName = timezone_name_from_abbr("", $this->mosque->getConf()->getTimezone() * 3600, false);
//        $expire->setTimezone(new \DateTimeZone($timezoneName));
//        return $expire < new \DateTime("now", new \DateTimeZone($timezoneName));
    }

    /**
     * @return boolean
     */
    public function isAvailable()
    {
        return $this->content && !$this->isExpired();
    }

    /**
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * @param string $color
     * @return FlashMessage
     */
    public function setColor(string $color): FlashMessage
    {
        $this->color = $color;
        return $this;
    }

    /**
     * @return string
     */
    public function getOrientation(): string
    {
        return $this->orientation;
    }

    /**
     * @param string $orientation
     * @return FlashMessage
     */
    public function setOrientation(string $orientation): FlashMessage
    {
        $this->orientation = $orientation;
        return $this;
    }

    /**
     * @return Mosque
     */
    public function getMosque(): Mosque
    {
        return $this->mosque;
    }
}

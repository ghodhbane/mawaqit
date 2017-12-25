<?php

namespace AppBundle\Service;

use Doctrine\ORM\Query;
use Doctrine\ORM\EntityManager;

class UserService {

    const SIGNATURE = "<br><br>Regards<br>Cordialement<br> تحياتي";
    
    /**
     * @var \Swift_Mailer 
     */
    private $mailer;

    /**
     * @var array 
     */
    private $emailFrom;
    
    /**
     * @var EntityManager 
     */
    private $em;
    
    public function __construct(\Swift_Mailer $mailer, $emailFrom, EntityManager $em) {
        $this->mailer = $mailer;
        $this->emailFrom = $emailFrom;
        $this->em = $em;
    }

    /**
     * Send email when mosque created
     */
    function sendEmailToAllUsers($data) {
        $subject = $data["subject"];
        $body = $data["content"] . self::SIGNATURE;
        $usersEmail = $this->em->createQueryBuilder()
                ->from("AppBundle:User", "u")
                ->select("u.email")
                ->where("u.enabled = 1")
                ->getQuery()
                ->getResult(Query::HYDRATE_SCALAR);
        
        $message = $this->mailer->createMessage();
        $message->setSubject($subject)
                ->setFrom([$this->emailFrom[0] => $this->emailFrom[1]])
                ->setTo($this->emailFrom[0])
                ->setBcc(array_map('current',$usersEmail))
                ->setBody($body, 'text/html');
        $this->mailer->send($message);
    }

}

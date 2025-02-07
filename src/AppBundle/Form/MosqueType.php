<?php

namespace AppBundle\Form;

use AppBundle\Entity\Mosque;
use AppBundle\Entity\User;
use AppBundle\Service\GoogleService;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichFileType;

class MosqueType extends AbstractType
{

    /**
     *
     * @var AuthorizationChecker
     */
    private $securityChecker;

    /**
     *
     * @var EntityManager
     */
    private $em;

    /**
     *
     * @var GoogleService
     */
    private $googleService;

    public function __construct(AuthorizationChecker $securityChecker, EntityManager $em, GoogleService $googleService)
    {
        $this->securityChecker = $securityChecker;
        $this->em = $em;
        $this->googleService = $googleService;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        /**
         * @var Mosque $mosque
         */
        $mosque = $builder->getData();
        $user = $mosque->getUser();
        if ($user instanceof User && $this->securityChecker->isGranted('ROLE_ADMIN')) {
            $builder
                ->add('user', HiddenType::class, [
                    'required' => true,

                ])
                ->add('user_complete', TextType::class, [
                    'data' => $user->getEmail(),
                    'label' => 'user',
                    'mapped' => false
                ]);

            $builder->get('user')
                ->addModelTransformer(new CallbackTransformer(
                    function ($user) {
                        return $user->getId();
                    },
                    function ($id) {
                        return $this->em->getRepository("AppBundle:User")->find($id);
                    }
                ));
        }

        $isAdmin = $this->securityChecker->isGranted('ROLE_ADMIN');
        $disabled = !$isAdmin && $mosque->isValidated();
        $uploadRequired = !$isAdmin && $mosque->isMosque() && !$mosque->isValidated();

        $typeOptions = [
            'required' => true,
            'label' => 'mosque.form.type.label',
            'constraints' => new Choice(["choices" => Mosque::TYPES]),
            'placeholder' => 'mosque.form.type.placeholder',
            'disabled' => $disabled,
            'choices' => array_combine([
                "mosque.types.mosque",
                "mosque.types.home",
            ], Mosque::TYPES)
        ];

        if ($mosque->getId() === null) {
            $typeOptions['data'] = "";
        }

        $builder
            ->add('name', null, [
                'label' => 'mosque.name',
                'attr' => [
                    'class' => 'keyboardInput',
                    'placeholder' => 'mosque.form.name.placeholder',
                ]
            ])
            ->add('type', ChoiceType::class, $typeOptions)
            ->add('slug', null, [
                'label' => 'mosque.slug',
                'label' => 'mosque.form.slug.label'
            ])
            ->add('associationName', null, [
                'label' => 'association_name',
                'attr' => [
                    'class' => 'keyboardInput',
                ]
            ])
            ->add('phone', null, [
                'label' => 'phone',
                'attr' => [
                    'class' => 'keyboardInput',
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'email.text',
                'required' => false,
                'attr' => [
                    'class' => 'keyboardInput',
                ]
            ])
            ->add('site', null, [
                'label' => 'site',
                'attr' => [
                    'class' => 'keyboardInput',
                ]
            ])
            ->add('address', null, [
                'label' => 'address',
                'disabled' => $disabled,
                'attr' => [
                    'class' => 'keyboardInput',
                    'placeholder' => 'mosque.form.address.placeholder',
                ]
            ])
            ->add('latitude', NumberType::class)
            ->add('longitude', NumberType::class)
            ->add('city', null, [
                'label' => 'city',
                'disabled' => $disabled,
                'attr' => [
                    'class' => 'keyboardInput',
                ]
            ])
            ->add('zipcode', null, [
                'label' => 'zipcode',
                'disabled' => $disabled,
                'attr' => [
                    'class' => 'keyboardInput',
                ]
            ])
            ->add('country', CountryType::class, [
                'placeholder' => 'mosque.form.country.placeholder',
                'label' => 'country',
                'disabled' => $disabled
            ])
            ->add('rib', null, [
                'label' => 'rib',
                'attr' => [
                    'placeholder' => 'mosque.form.rib.placeholder',
                    'class' => 'keyboardInput',
                ]
            ])
            ->add('addOnMap', CheckboxType::class, [
                'label' => 'mosque.form.addOnMap.label',
                'required' => false,
            ])
            ->add('womenSpace', CheckboxType::class, [
                'required' => false,
            ])
            ->add('handicapAccessibility', CheckboxType::class, [
                'required' => false,
            ])
            ->add('janazaPrayer', CheckboxType::class, [
                'required' => false,
            ])
            ->add('aidPrayer', CheckboxType::class, [
                'required' => false,
            ])
            ->add('childrenCourses', CheckboxType::class, [
                'required' => false,
            ])
            ->add('adultCourses', CheckboxType::class, [
                'required' => false,
            ])
            ->add('ramadanMeal', CheckboxType::class, [
                'required' => false,
            ])
            ->add('ablutions', CheckboxType::class, [
                'required' => false,
            ])
            ->add('parking', CheckboxType::class, [
                'required' => false,
            ])
            ->add('justificatoryFile', VichFileType::class, [
                'required' => $uploadRequired,
                'translation_domain' => 'messages',
                'label' => 'mosque.form.justificatoryFile.label',
                'download_uri' => false,
                'attr' => [
                    'class' => 'form-control',
                    'help' => 'mosque.form.justificatoryFile.help',
                ],
                'constraints' => new File([
                    'maxSize' => '10M',
                    'mimeTypes' => [
                        'application/pdf',
                        'application/x-pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'image/png',
                        'image/jpeg',
                    ]
                ])
            ])
            ->add('file1', ImageType::class, [
                'required' => $uploadRequired,
                'label' => 'mosque.form.file1.label',
                'attr' => [
                    'class' => 'form-control',
                    'help' => 'mosque.form.file1.help',
                ]
            ])
            ->add('file2', ImageType::class, [
                'label' => 'mosque.form.file2.label'
            ])
            ->add('file3', ImageType::class, [
                'label' => 'mosque.form.file3.label'
            ])
            ->add('otherInfo', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'maxlength' => '200',
                    'rows' => '3',
                    'class' => 'keyboardInput',
                    'placeholder' => 'mosque.form.otherInfo.placeholder',
                ]
            ])
            ->add('save', SubmitType::class, [
                'label' => 'save',
                'attr' => [
                    'class' => 'btn btn-primary',
                ]
            ])
            ->addEventListener(FormEvents::SUBMIT, array($this, 'onPostSetData'));
    }

    /**
     * @param FormEvent $event
     * @throws \AppBundle\Exception\GooglePositionException
     */
    public function onPostSetData(FormEvent $event)
    {
        $oldMosque = null;
        /** @var Mosque $mosque */
        $mosque = $event->getData();

        if ($mosque->getId()) {
            $oldMosque = $this->em->getUnitOfWork()->getOriginalEntityData($mosque);
        }

        if (null === $oldMosque || $oldMosque['address'] !== $mosque->getAddress() || $oldMosque['country'] !== $mosque->getCountry() || $oldMosque['city'] !== $mosque->getCity() || $oldMosque['zipcode'] !== $mosque->getZipcode()) {
            $position = $this->googleService->getPosition($mosque);
            $mosque->setLongitude($position['lng']);
            $mosque->setLatitude($position['lat']);
            $mosque->getConfiguration()->setTimezoneName($position['timezone']);
        }

        if ($mosque->getType() === "home") {
            $mosque->resetToHome();
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'label_format' => 'mosque.form.%name%.label',
            'data_class' => Mosque::class,
            'validation_groups' => function (FormInterface $form) {
                $mosque = $form->getData();

                if ($mosque->isMosque()) {
                    return ['Default', 'Mosque'];
                }

                return ['Default'];
            },
        ));
    }

    public function getBlockPrefix()
    {
        return '';
    }

}

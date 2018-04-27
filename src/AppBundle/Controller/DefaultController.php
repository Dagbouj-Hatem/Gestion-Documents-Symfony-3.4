<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
/*  form type */ 
use Symfony\Component\Form\Extension\Core\Type\TextType; 
use Symfony\Component\Form\Extension\Core\Type\EmailType; 
use Symfony\Component\Form\Extension\Core\Type\RepeatedType; 
use Symfony\Component\Form\Extension\Core\Type\PasswordType; 

/* Data */
use AppBundle\Form\UserType;
use AppBundle\Form\DocumentType;
use AppBundle\Entity\User;
use AppBundle\Entity\Document;

/* auth */ 
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
/* file system */ 
use Symfony\Component\Filesystem\Filesystem;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(Document::class);
        $documents= $repository->findAll(); 
        return $this->render('home.html.twig', array('documents'=> $documents ,));
    }

    /**
     * @Route("/register", name="register")
     */
    public function action_register(Request $request)
    {   
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
       // 2) handle the submit (will only happen on POST)
        /*print_r( $request->get('form'));
        dump($user);
        die;*/
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            // 3) Encode the password (you could also do this via Doctrine listener)
           /* $password = $passwordEncoder->encodePassword($user, $user->getPlainPassword());
            $user->setPassword($password);*/

            // 4) save the User!
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            // ... do any other work - like sending them an email, etc
            // maybe set a "flash" success message for the user

            return $this->redirectToRoute('login');
        }
         return $this->render('register.html.twig', ['form' => $form->createView()]);
    }
    
     /**
     * @Route("/login", name="login")
     */
    public function action_login(Request $request ,AuthenticationUtils $authenticationUtils)
    {  
          if ($this->get('security.authorization_checker')->isGranted('ROLE_USER'))
        {
           return $this->redirectToRoute('dashboard');
        }
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

       return $this->render('login.html.twig',  array(
                            '_username' => $lastUsername,
                            'error'         => $error,
                             ));
    }

    /**
     * @Route("/reset", name="reset")
     */
    public function action_reset()
    {
       return $this->render('reset.html.twig');
    }

     /**
     * @Route("/dashboard", name="dashboard")
     */
    public function action_bashboard()
    {
        $user= $this->getUser(); 
       return $this->render('dashboard.html.twig', array('user'=> $user,));
    }

    /**
     * @Route("/dashboard/documents/list", name="documents")
     * @Security("has_role('ROLE_USER')")
     */
    public function action_documents()
    {   
        $user= $this->getUser(); // current user authentifed
         // get all documents from database
        $repository = $this->getDoctrine()->getRepository(Document::class);
        $documents= $repository->findAll(); 
        //dump($documents); die;
       return $this->render('documents.html.twig', array('user'=> $user,'documents'=> $documents ,));
    } 

    /**
     * @Route("/dashboard/documents/add", name="add_document")
     */
    public function action_documents_add(Request $request)
    {   $user= $this->getUser(); 
        $document= new Document();
        $form = $this->createForm(DocumentType::class, $document);
       // 2) handle the submit (will only happen on POST)
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { 
            // $file stores the uploaded PDF file
            /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */
            $file = $document->getUrl();

            $fileName = md5($file. time()).'.'.$file->guessExtension();

            // moves the file to the directory where brochures are stored
            $file->move(
                $this->getParameter('brochures_directory'),
                $fileName
            );

            // updates the 'brochure' property to store the PDF file name
            // instead of its contents
            $document->setUrl($fileName);
            // 4) save the Doc!
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($document);
            $entityManager->flush(); 

            return $this->redirectToRoute('documents');
        }
       return $this->render('add_doc.html.twig', array('user'=> $user,'form' => $form->createView(),));
    }
    /**
     * @Route("/dashboard/document/{name}", name="get_document")
     *      to download a file by name
     */
    public function docummentbyurl($name)
    {
        $pdfPath = $this->getParameter('brochures_directory').$name;
        return $this->file($pdfPath);
    }
    /**
     * @Route("/dashboard/document/delete/{id}", name="delete_document")
     *      to delete a file by id
     */
    public function deletedocummentbyid($id)
    { 
        //1.0 find document by  id 
        $entityManager = $this->getDoctrine()->getManager();
        $doc = $entityManager->getRepository('AppBundle:Document')->find($id);
        // 2.0 delete file from disk 
        $fs =  new Filesystem();
        $fs->remove($this->getParameter('brochures_directory').$doc->getUrl());
        // 3.0 delete from databdae  
        $entityManager->remove($doc);
        $entityManager->flush();
        return $this->redirectToRoute('documents');
    }
    /**
     * @Route("/dashboard/document/update/{id}", name="update_document")
     *      to update a file by id
     */
    public function updatedocummentbyid(Request $request, $id)
    {  $user= $this->getUser(); 
        //1.0 find document by  id 
        $entityManager = $this->getDoctrine()->getManager();
        $doc = $entityManager->getRepository('AppBundle:Document')->find($id);
        $file_name=$doc->getUrl();
        $form = $this->createForm(DocumentType::class, $doc);
              // 2) handle the submit (will only happen on POST)
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { 
            // $file stores the uploaded PDF file
            /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */
            //dump($doc); die ;

            if($doc->getUrl() != null)
            {
                // 1.0 delete l'ancien file 
                 $fs =  new Filesystem();
                 $fs->remove($this->getParameter('brochures_directory').$file_name);
                 // 2.0 upload  the second file & save database 
                        $file = $doc->getUrl();
                        $fileName = md5($file. time()).'.'.$file->guessExtension();
                        // moves the file to the directory where brochures are stored
                        $file->move(
                            $this->getParameter('brochures_directory'),
                            $fileName
                        );
                        // updates the 'brochure' property to store the PDF file name
                        // instead of its contents
                        $doc->setUrl($fileName);
            }
            else
            {
                $doc->setUrl($file_name);
            }
          
            // 4) save the Doc!
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($doc);
            $entityManager->flush(); 

            return $this->redirectToRoute('documents');
        }
       return $this->render('update_doc.html.twig', array('user'=> $user,'form' => $form->createView(),));
    }
    /**
     * @Route("/dashboard/settings", name="settings")
     */
    public function action_settings(Request $request)
    {  $user= $this->getUser();   
        $form = $this->createForm(UserType::class, $user);
       // 2) handle the submit (will only happen on POST)
        /*print_r( $request->get('form'));
        dump($user);
        die;*/
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) { 

            // 4) save the User!
            $entityManager = $this->getDoctrine()->getManager();
            $userF = $entityManager->getRepository('AppBundle:User')->find($user->getId());
            //dump($userF); die;
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('dashboard');
        }
         return $this->render('settings.html.twig', ['form' => $form->createView(), 'user'=> $user ,]);
    } 

    /**
     * @Route("logout", name="logout")
     */
    public function action_logout()
    {
       return $this->redirect('/');
    }

}

<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController {

  /**
   * @Route("/profile/{displayname}", name="profile", defaults={"displayname"="0"})
   */
  public function index($displayname) {
    $em = $this->getDoctrine()->getManager();

    //checks if user is valid
    if ($displayname == '0') {
      $user = $this->getUser();
    } else {
      $user = $em->getRepository(User::class)->findOneBy(['displayname' => $displayname]);
      if (!$user) {
        return $this->redirectToRoute('index');
      }
    }

    //calculate age of user
    $age = date_diff(date_create($user->getBirthdate()->format('Y-m-d H:i:s')), date_create('now'))->y;

    return $this->render('profile/index.html.twig', [
        'user' => $user,
        'age' => $age,
        'controller_name' => 'ProfileController',
    ]);
  }

  /**
   * @Route("/profile/edit/{id}", name="profile_edit")
   */
  public function edit(Request $request, $id) {
    $em = $this->getDoctrine()->getManager();

    $user = $em->getRepository(User::class)->find($id);
    if (!$user || $this->getUser() != $user) {
      if (!$this->isGranted("ROLE_ADMIN")) {
        return $this->redirectToRoute('index');
      }
    }

    $form = $this->createForm(ProfileType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {

      $avatarFile = $form['avatarFile']->getData();
      if ($avatarFile) {
        //$originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
        //$safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
        //$newFilename = $safeFilename.'-'.uniqid().'.'.$avatarFile->guessExtension();
        $filename = md5(uniqid()) . '.' . $avatarFile->guessExtension();

        try {
          $avatarFile->move(
              $this->getParameter('avatars_directory'),
              $filename
          );
        } catch (FileException $e) {
          throw new \Exception('Something went wrong!');
        }

        $user->setAvatar($filename);
      }

      $em->persist($user);
      $em->flush();
      $this->addFlash('success', "Profile updated successfully!");
      return $this->redirectToRoute('profile', ['displayname' => $user->getDisplayname()]);
    }

    return $this->render('profile/edit.html.twig', [
      'user' => $user,
      'form' => $form->createView(),
    ]);
  }
}

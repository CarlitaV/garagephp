<?php
namespace App\Controllers;

use App\Models\User;
use App\Security\Validator;
use App\Security\TokenManager;
use App\Utils\Logger;

/**
 * Cette classe gère les actions liées a l'autentification et a l'inscription des utilisateur
 */

class AuthController extends BaseController{

    //Attributs
    private User $userModel;
    private TokenManager $tokenManager;
    private Logger $logger;

    /**Constructeur est appellé à chaque création d'un objet AuthController  on en profite 
     * pour instancier les models dont on aura besoin
     * */

    public function __construct(){
        
        parent::__construct();
        $this->userModel = new User();
        $this->tokenManager = new TokenManager();
        $this->logger = new Logger();
    }
    /**
     * Méthode qui affiche la page avec le formulaire de connexion
     */
    public function showLogin(): void{
        $this->render('auth/login',[
            'title' => 'Connexion',
            'csrf_token' => $this->tokenManager->generateCrsrToken()
        ]);
    }

    public function login():void{
        //on s'assure que la requete est de type POST
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            $this->response->redirect('/login');
        }

        $data = $this->getPostData();

        //Validation du jeton CSRF
        if(!$this->tokenManager->validateCsrfToken($data['csrf_token'] ?? '')){
            $this->response->error('Token de sécurité invalide.', 403);
        }
        //Le modele User s'occupe de la logique d'authentification 
        $user = $this->userModel->authenticate($data['email'], $data['password']);
        
        if($user){
            //Si l'authentification réussit, on stocke les informations en session
            $_SESSION['user_id'] = $user->getId();
            $_SESSION['user_role'] = $user->getRole();
            $_SESSION['user_username'] = $user->getUsername();

            $this->response->redirect('/cars');
        }else{
            
            //sinon echec de l'authentification on ré-affiche le formulaire avec un message d'erreur
            $this->render('auth/login', [
                'title' => 'Connexion',
                'error' => 'Email ou mot de passe incorrect.',
                'old' => ['email' => $data['email']],
                'csrf_token' => $this->tokenManager->generateCsrfToken()
            ]);
        }
    }

    /**
     * Affichage du formulaire d'inscription
     */

    public function showRegister():void{
        $this->render('auth/register',[
            'title'=>'Inscription',
            'csrf_token'=> $this->token->generateCsrfToken()
        ]);
    }

    /**
     * Traitements des données soumission formulaire inscription
     */
    public function register():void{
        //On verifie que la methode est bien POST sinon on redirige vers register
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            $this->response->redirect('/login');
        }
        $data = $this->getPostData();

        //Validation du jeton CSRF
        if(!$this->tokenManager->validateCsrfToken($data['csrf_token'] ?? '')){
            $this->response->error('Token de sécurité invalide.', 403);
        }

        //Validation des données du formulaire
        $errors = $this->validator($data,[
            'username'=> 'required|min:3|max:50',
            'email'=> 'required|email',
            'password'=> 'required|min:9',
            'password_confirm'=> 'required|same:password'
        ]);

        if(!empty($errors)){
            $this->render('auth/register', [
                'title'=>'Inscription',
                'errors'=> $errors,
                'old'=>$data,
                'csrf_token'=> $this->tokenManager->generateCsrfToken()
            ]);
            return;
        }

        //Vérification de l'email si déjà existant en BDD
        if($this->userModel->findByEmail($data['email'])){
            
            $this->render('auth/register', [
                'title' => 'Inscription',
                // On ajoute une erreur au champ email pour l'afficher
                'errors' => ['email' => ['Cette adresse email est déjà utilisée.']],
                'old' => $data,
                'csrf_token' => $this->tokenManager->generateCsrfToken()
            ]);
            return;
        }

        /**
         * Si tout est correcte alors on crée un nouvel utilisateur
         */
        try{

            //On instancie un nouvel utilisateur
            $newUser = new User();

            //On utilise les setters pour assigner les valeurs(inclut la validation et le hashage du MDP)
            $newUser->setUsername($data['username'])
                    ->setEmail($data['email'])
                    ->setPassword($data['password'])
                    ->setRole($data['user']); // role par défaut

            //On sauvegarde en BDD
            if($newUser->save()){

                //Si la création réussi, on connecte automatiquement l'utilisateur
                $_SESSION['user_id'] = $newUser->getId();
                $_SESSION['user_role'] = $newUser->getRole();
                $_SESSION['user_username'] = $newUser->getUsername();
                $this->response->redirect('/cars');

            }else{

                //Si la sauvegarde échoue
                throw new \Exception("La création du compte à échoué.");
            }

        }catch(\Exception $e){

            $this->render('auth/register', [

                'title' => 'Inscription',
                'error' => "Erreur : " . $e->getMessage(), // Erreur générale
                'old' => $data,
                'csrf_token' => $this->tokenManager->generateCsrfToken()
            ]);

        }

    }

        /**
         * Méthode de déconnexion avec destruction de la session
         */

        public function logout(): void{

            //On vérifie que la méthode est bien POST 
            if($_SERVER['REQUEST_METHOD'] !== 'POST'){

                $this->response->redirect('/');
            }

            /**
             * Détruit toutes les données de la session actuelle
             */
            session_destroy();

            //Redirige vers la page de connexion
            $this->response->redirect('/login');

        } 

}
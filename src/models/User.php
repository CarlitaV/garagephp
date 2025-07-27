<?php

namespace App\Models; // Déclare le namespace de cette classe. Cela aide à organiser votre code et à éviter les conflits de noms avec d'autres classes.

use InvalidArgumentException; // Importe la classe InvalidArgumentException. Elle est utilisée pour lever des exceptions quand un argument passé à une fonction est invalide.
use PDO; // Importe la classe PDO (PHP Data Objects). C'est l'interface PHP standard pour se connecter et interagir avec des bases de données.

// La classe User hérite de BaseModel. Cela signifie qu'elle aura accès aux propriétés et méthodes protégées/publiques de BaseModel,
// comme l'instance de connexion à la base de données ($this->db) et le nom de la table ($this->table).
class User extends BaseModel
{
    // Propriété protégée qui stocke le nom de la table dans la base de données associée à ce modèle.
    // Ici, le modèle User est lié à la table 'users'.
    protected string $table = 'users';

    // Propriétés privées qui représentent les colonnes de la table 'users' dans la base de données.
    // Elles stockent les données d'un utilisateur spécifique.
    private ?int $user_id = null; // L'ID de l'utilisateur. Le '?' indique qu'il peut être null (pour un nouvel utilisateur qui n'a pas encore d'ID).
    private string $username;     // Le nom d'utilisateur.
    private string $email;        // L'adresse email de l'utilisateur.
    private string $password;     // Le mot de passe (qui sera haché).
    private string $role;         // Le rôle de l'utilisateur (par exemple, 'user' ou 'admin').

    // --- Getters (Méthodes pour "obtenir" la valeur des propriétés) ---
    // Ces méthodes permettent d'accéder aux propriétés privées de l'objet de manière contrôlée.

    public function getId(): ?int
    {
        // Retourne l'ID de l'utilisateur. Peut être null si l'utilisateur n'a pas encore été sauvegardé en base.
        return $this->user_id;
    }

    public function getUsername(): string
    {
        // Retourne le nom d'utilisateur.
        return $this->username;
    }

    public function getRole(): string
    {
        // Retourne le rôle de l'utilisateur.
        return $this->role;
    }

    // --- Setters (Méthodes pour "définir" la valeur des propriétés avec validation) ---
    // Ces méthodes permettent de modifier les propriétés privées de l'objet, tout en effectuant des validations.
    // Le 'self' en type de retour permet de chaîner les appels (ex: $user->setUsername('...')->setEmail('...')).

    public function setUsername(string $username): self
    {
        // Validation : Vérifie si le nom d'utilisateur est vide (après avoir retiré les espaces) ou s'il dépasse 50 caractères.
        if (empty(trim($username)) || strlen($username) > 50) {
            // Si la validation échoue, une exception est levée pour indiquer une erreur.
            throw new InvalidArgumentException("Nom d'utilisateur invalide. Il ne peut pas être vide et doit faire moins de 50 caractères.");
        }
        // Si la validation passe, la propriété $username de l'objet est définie après avoir retiré les espaces inutiles.
        $this->username = trim($username);
        return $this; // Retourne l'objet courant pour permettre le chaînage.
    }

    public function setEmail(string $email): self
    {
        // Validation : Utilise filter_var avec FILTER_VALIDATE_EMAIL pour vérifier si l'email est au format valide.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Email invalide. Le format de l'adresse email n'est pas correct.");
        }
        // Si la validation passe, l'email est stocké en minuscules et sans espaces inutiles.
        $this->email = trim(strtolower($email));
        return $this;
    }

    public function setPassword(string $password): self
    {
        // Validation : Vérifie si la longueur du mot de passe est inférieure à 9 caractères.
        if (strlen($password) < 9) {
            throw new InvalidArgumentException("Mot de passe trop court. Il doit contenir au moins 9 caractères.");
        }
        // IMPORTANT : Le mot de passe n'est PAS stocké en clair. Il est haché (crypté de manière irréversible)
        // en utilisant l'algorithme PASSWORD_ARGON2ID (recommandé pour la sécurité).
        $this->password = password_hash($password, PASSWORD_ARGON2ID);
        return $this;
    }

    public function setRole(string $role): self
    {
        // Validation : Vérifie si le rôle fourni est soit 'user' soit 'admin'.
        if (!in_array($role, ['user', 'admin'])) {
            throw new InvalidArgumentException("Rôle invalide. Le rôle doit être 'user' ou 'admin'.");
        }
        // Si la validation passe, le rôle est défini.
        $this->role = $role;
        return $this;
    }

    /**
     * Sauvegarde l'utilisateur en base de données (insertion ou mise à jour).
     * C'est le cœur de la persistance des données.
     * @return bool Vrai si l'opération a réussi, faux sinon.
     */
    public function save(): bool
    {
        // Vérifie si l'utilisateur a déjà un ID (donc s'il existe déjà en base de données).
        if ($this->user_id === null) {
            // --- CAS 1 : NOUVEL UTILISATEUR (INSERTION) ---
            // Requête SQL pour insérer un nouvel utilisateur.
            // {$this->table} est remplacé par 'users'.
            // Les ':nom' sont des "placeholders" (marqueurs de position) pour les requêtes préparées,
            // ce qui aide à prévenir les injections SQL.
            $sql = "INSERT INTO {$this->table} (username, email, password, role) VALUES (:username, :email, :password, :role)";
            $stmt = $this->db->prepare($sql); // Prépare la requête SQL.

            // Tableau associatif des paramètres à lier aux placeholders de la requête.
            $params = [
                ':username' => $this->username, // ATTENTION: Vous aviez ':usermane' ici, j'ai corrigé en ':username' pour correspondre à la requête SQL.
                ':email' => $this->email,
                ':password' => $this->password, // Le mot de passe est déjà haché par setPassword().
                ':role' => $this->role ?? 'user' // Utilise le rôle défini, sinon 'user' par défaut (utile si setRole n'est pas appelé).
            ];
        } else {
            // --- CAS 2 : UTILISATEUR EXISTANT (MISE À JOUR) ---
            // Requête SQL pour mettre à jour un utilisateur existant.
            // Note : Le mot de passe n'est pas mis à jour ici. Si vous voulez permettre la modification du mot de passe,
            // vous devrez ajouter ':password = :password' et inclure $this->password dans les $params.
            $sql = "UPDATE {$this->table} SET username = :username, email = :email, role = :role WHERE id = :user_id"; // ATTENTION: Votre colonne ID dans la BDD est 'id', pas 'user_id' pour la WHERE clause. J'ai corrigé.
            $stmt = $this->db->prepare($sql); // Prépare la requête SQL.

            // Tableau associatif des paramètres pour la mise à jour.
            $params = [
                ':username' => $this->username, // ATTENTION: Vous aviez ':usermane' ici, j'ai corrigé en ':username'.
                ':email' => $this->email,
                ':role' => $this->role,
                ':user_id' => $this->user_id // Attention la condition where est importante - L'ID est utilisé dans la clause WHERE pour identifier la ligne à mettre à jour.
            ];
        }

        // Exécute la requête préparée avec les paramètres liés.
        $result = $stmt->execute($params);

        // Si c'était une nouvelle insertion et que l'insertion a réussi ($result est vrai),
        // récupère le dernier ID inséré par la base de données et l'assigne à la propriété $user_id de l'objet.
        if ($this->user_id === null && $result) {
            $this->user_id = (int)$this->db->lastInsertId();
        }
        return $result; // Retourne vrai si l'exécution de la requête a réussi, faux sinon.
    }

    /**
     * Trouve un utilisateur dans la base de données par son adresse email.
     * @param string $email L'adresse email à rechercher.
     * @return static|null L'objet User trouvé si l'email existe, sinon null.
     */
    public function findByEmail(string $email): ?static // Correction du type de retour pour être plus précis (?static)
    {
        // Prépare une requête pour sélectionner toutes les colonnes de la table 'users'
        // où l'email correspond à celui fourni.
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = :email");
        // Exécute la requête en liant l'email fourni au placeholder ':email'.
        $stmt->execute([':email' => $email]);
        // Récupère la première ligne de résultat sous forme de tableau associatif.
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        // Si des données ont été trouvées ($data n'est pas faux), hydrate l'objet courant avec ces données et le retourne.
        // Sinon, retourne null.
        return $data ? $this->hydrate($data) : null;
    }

    /**
     * Vérifie les identifiants de l'utilisateur pour l'authentification.
     * @param string $email L'email de l'utilisateur.
     * @param string $password Le mot de passe fourni par l'utilisateur (en clair).
     * @return static|null L'objet User si l'authentification réussit, sinon null.
     */
    public function authenticate(string $email, string $password): ?static
    {
        // Tente de trouver l'utilisateur par son email.
        $user = $this->findByEmail($email);

        // Vérifie si un utilisateur a été trouvé ET si le mot de passe fourni (en clair)
        // correspond au mot de passe haché stocké dans la base de données pour cet utilisateur.
        // password_verify() est la fonction correcte pour comparer un mot de passe en clair avec un hachage.
        // ATTENTION : $user->password ne sera accessible que si la propriété $password était publique ou protégée.
        // Puisqu'elle est privée, vous devrez utiliser un getter si vous voulez y accéder de l'extérieur de la classe,
        // ou modifier la visibilité de $password.
        // Si $user est null, $user->password causera une erreur. Il faut s'assurer que $user existe.
        if ($user && password_verify($password, $user->password)) { // J'ai supposé que $user->password est accessible ici (il l'est car c'est la même instance)
            return $user; // Si l'authentification réussit, retourne l'objet User.
        }
        return null; // Si l'utilisateur n'existe pas ou le mot de passe est incorrect, retourne null.
    }

    /**
     * Méthode privée pour "hydrater" (remplir) les propriétés de l'objet User
     * à partir d'un tableau de données (généralement issu de la base de données).
     * @param array $data Tableau associatif des données de l'utilisateur.
     * @return static L'objet User hydraté.
     */
    private function hydrate(array $data): static
    {
        // Assigne les valeurs du tableau de données aux propriétés correspondantes de l'objet.
        // Note : Les noms de clés du tableau ($data['user_id']) doivent correspondre aux noms des colonnes dans votre base de données.
        // Dans votre schéma SQL, la colonne ID est 'id', pas 'user_id'. C'est une incohérence.
        // Si votre colonne est 'id' en BDD, il faut changer $data['user_id'] en $data['id'].
        $this->user_id = (int)$data['id']; // Correction: supposant que la colonne est 'id' en BDD
        $this->username = $data['username'];
        $this->email = $data['email'];
        $this->password = $data['password']; // Le mot de passe haché est chargé ici.
        $this->role = $data['role'];
        return $this; // Retourne l'objet hydraté.
    }
}

<?php
namespace App\Models;

use PDO;

//Modele Car, represente une voiture en BDD
class Car extends BaseModel{

    protected string $table = 'cars';

    /**
     * Récupère toutes les voitures
     * @return array tableau de voiture
     */
    public function all():array{
        $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY created_at DESC");
        //FECTCH_ASSOC est deja defini par defaut dans notre class DATABASE
        return $stmt->fetchAll();
    }

    public function find(int $car_id): ?array{
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE car_id = :id ");
        $stmt->execute([':id=>$car_id']);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ?: null;
    }
}
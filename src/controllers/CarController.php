<?php
namespace App\Controllers; //Pour lui dire ou son les fichiers

use App\Models\Car;

class CarController extends BaseController{
    //Dans cette class j'aurai une seul methode
    public function index():void{
        $this->requireAuth();
        $this->render('car/index',[
            'title'=>'Tableau de bord voiture',
            'cars'=>(new Car())->all()
        ]);
    }
}
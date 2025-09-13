<?php

namespace App\Livewire\Admin;

use App\Models\BetCollection10To20Model;
use App\Models\BetCollection5To20Model;
use App\Models\BetCollectionRedoblonaModel;
use App\Models\FigureOneModel;
use App\Models\FigureTwoModel;
use App\Models\QuinielaModel;
use App\Models\PrizesModel;
use Livewire\Attributes\Layout;
use Livewire\Component;

class PaymentTable extends Component
{
    #[Layout('layouts.app')]
    public function render()
    {
        $quinielas = QuinielaModel::all();
        $prizes = PrizesModel::all();
        $figureOne = FigureOneModel::all();
        $figureTwo = FigureTwoModel::all();
        $BetCollectionRedoblonaModel = BetCollectionRedoblonaModel::all();
        $BetCollection5To20Model = BetCollection5To20Model::all();
        $BetCollection10To20Model = BetCollection10To20Model::all();

        return view('livewire.admin.payment-table', [
            'quinielas' => $quinielas,
            'prizes' => $prizes,
            'figureone' => $figureOne,
            'figuretwo' => $figureTwo,
            'betcollectionredoblona' => $BetCollectionRedoblonaModel,
            'betcollection5to20' => $BetCollection5To20Model,
            'betcollection10to20' => $BetCollection10To20Model
        ]);
    }
}

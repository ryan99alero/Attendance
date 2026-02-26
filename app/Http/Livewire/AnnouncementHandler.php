<?php

namespace App\Http\Livewire;

use App\Traits\HandlesAnnouncementActions;
use Livewire\Component;

class AnnouncementHandler extends Component
{
    use HandlesAnnouncementActions;

    public function render()
    {
        return view('livewire.announcement-handler');
    }
}

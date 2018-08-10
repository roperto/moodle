<?php

namespace teamevalreport_scores\output;

use core_user;
use stdClass;
use user_picture;

class scores_report implements \renderable, \templatable {

    public $scores;

    public function __construct($data) {
        $display_scores = [];

        foreach($data as $userid => $datum) {
            $user = core_user::get_user($userid, user_picture::fields());
            $c = clone $datum;
            $c->user = $user;
            $display_scores[] = $c;
        }
        $this->scores = $display_scores;
    }

    public function export_for_template(\renderer_base $output) {
        $ctx = [];
        foreach($this->scores as $score) {
            $userpic = $output->render(new user_picture($score->user));
            $fullname = fullname($score->user);
            $evalscore = round($score->score, 2);
            $intergrade = $score->intermediategrade;
            $noncomplete = round($score->noncompletionpenalty * 100, 2);
            $finalgrade = $score->finalgrade;
            $ctx[] = ['userpic' => $userpic, 'fullname' => $fullname, 'score' => $evalscore, 'intermediategrade' => $intergrade, 'noncompletionpenalty' => $noncomplete, 'finalgrade' => $finalgrade];
        }
        return ['scores' => $ctx];
    }

}
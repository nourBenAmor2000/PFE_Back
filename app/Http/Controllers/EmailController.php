<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;

class EmailController extends Controller
{
    public function sendWelcomeEmail(){
        $toEmail = 'benamor.nour@esprit.tn';
        $message = 'Welcome to Programming fields';
        $subject = 'Welcome Email';
        $response = Mail::to($toEmail)->send(new WelcomeEmail($message , $subject));
   
   dd($response);
    }
}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 
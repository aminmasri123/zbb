<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;use Illuminate\Mail\Mailable;use Illuminate\Queue\SerializesModels;
class ParticipantApplicationSubmitted extends Mailable {use Queueable,SerializesModels;public function __construct(public string $mailSubject,public string $bodyText,public array $pdfAttachments){}public function build(){ $mail=$this->subject($this->mailSubject)->html(nl2br(e($this->bodyText)));foreach($this->pdfAttachments as $file)$mail->attachData($file['data'],$file['name'],['mime'=>'application/pdf']);return $mail;}}

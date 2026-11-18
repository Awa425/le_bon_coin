<?php

namespace App\Enums;

/** Cycle de traitement d'une photo : envoi direct → traitement asynchrone → prête (ou échec). */
enum PhotoStatus: string
{
    /** URL d'envoi délivrée, fichier pas encore confirmé. */
    case Pending = 'pending';
    /** Fichier reçu, déclinaisons en cours de génération. */
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}

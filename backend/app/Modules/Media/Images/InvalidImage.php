<?php

namespace App\Modules\Media\Images;

use RuntimeException;

/** Fichier refusé (format, taille, image illisible) : erreur définitive, inutile de réessayer. */
class InvalidImage extends RuntimeException {}

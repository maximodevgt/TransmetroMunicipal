<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una operación viola una regla de negocio. El mensaje se muestra tal cual al usuario
 * (bootstrap/app.php la convierte en un toast de error y regresa al formulario).
 */
class ReglaNegocioException extends RuntimeException {}

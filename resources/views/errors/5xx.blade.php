{{-- Cualquier otro error 5xx que no tenga su propia página. --}}
@extends('errors.layout')

@section('codigo', ($exception ?? null)?->getStatusCode() ?? '500')
@section('icono', 'error')
@section('titulo', 'El servidor no pudo responder')
@section('mensaje', 'Ocurrió un problema en el servidor. Intente de nuevo en unos momentos.')

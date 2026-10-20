{{-- Cualquier otro error 4xx (405, 413...) que no tenga su propia página. --}}
@extends('errors.layout')

@section('codigo', ($exception ?? null)?->getStatusCode() ?? '400')
@section('icono', 'advertencia')
@section('titulo', 'No se pudo atender la solicitud')
@section('mensaje', 'La solicitud no es válida. Vuelva a la pantalla anterior e intente de nuevo.')

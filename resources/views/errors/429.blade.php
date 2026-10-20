@extends('errors.layout')

@php($espera = (int) (($exception ?? null)?->getHeaders()['Retry-After'] ?? 60))
@section('codigo', '429')
@section('icono', 'reloj')
@section('titulo', 'Demasiados intentos')
@section('mensaje', 'Hizo demasiadas solicitudes seguidas. Espere '.($espera > 60 ? ceil($espera / 60).' minutos' : $espera.' segundos').' y vuelva a intentarlo.')

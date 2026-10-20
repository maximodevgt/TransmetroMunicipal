@extends('errors.layout')

@section('codigo', '403')
@section('icono', 'error')
@section('titulo', 'Acceso restringido')
{{-- Usa el motivo de abort(403, '...') cuando existe; si no, el mensaje general. --}}
@section('mensaje', ($exception ?? null)?->getMessage() ?: 'No tiene permiso para realizar esta acción.')

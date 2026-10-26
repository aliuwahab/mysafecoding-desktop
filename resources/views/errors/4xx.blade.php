@extends('errors.layout')
@section('code', $exception->getStatusCode())
@section('title', 'That request didn\'t work')
@section('message')
    <p>Something about that request wasn't right. Head back to the scanner and try again.</p>
@endsection

@extends('errors.layout')
@section('code', $exception->getStatusCode())
@section('title', 'Something went wrong on our side')
@section('message')
    <p>Sorry, that's our fault, not yours. Please try again in a moment.</p>
@endsection
@section('note', 'If you were scanning, any files you sent are still deleted automatically. Nothing is kept.')

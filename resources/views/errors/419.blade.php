@extends('errors.layout')
@section('code', '419')
@section('title', 'This page expired')
@section('message')
    <p>The page was open for a long time, so its security token expired. Reload and try again.</p>
@endsection
@section('actions')
    <a class="btn btn-primary" href="{{ url('/') }}">Reload the scanner</a>
@endsection

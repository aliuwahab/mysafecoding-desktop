@extends('errors.layout')
@section('code', '503')
@section('title', 'Back in a moment')
@section('message')
    <p>CheckMyCode is being updated or is briefly unavailable. This usually takes less than a minute.</p>
@endsection
@section('actions')
    <a class="btn btn-primary" href="{{ url()->current() }}">Try again</a>
@endsection

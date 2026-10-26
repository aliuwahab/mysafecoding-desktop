@extends('errors.layout')
@section('code', '403')
@section('title', 'Access denied')
@section('message')
    <p>You don't have access to this page or scan. Scan results are visible only to the browser that ran the scan.</p>
@endsection

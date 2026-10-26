@extends('errors.layout')
@section('code', '429')
@section('title', 'You\'ve hit the scan limit')
@section('message')
    <p>To keep CheckMyCode free for everyone, each network can run {{ config('checkmycode.web.scans_per_hour', 10) }} scans per hour. Please try again a little later.</p>
    <p>Need more? The desktop app and the command-line tool run the same scanner on your own machine, with no limits.</p>
@endsection

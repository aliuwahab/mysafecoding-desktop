@extends('errors.layout')
@section('code', '413')
@section('title', 'That project is too large')
@section('message')
    <p>The web scanner accepts up to {{ config('checkmycode.web.max_upload_mb', 50) }} MB after compression. Add exclusions (build folders, assets, data files), scan a sub-folder, or use the desktop app or command-line tool for big projects.</p>
@endsection
@section('note', 'Nothing from this attempt was kept.')

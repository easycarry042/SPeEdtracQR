@extends('errors.minimal')

@section('title', 'Session Expired')
@section('code', 'Error 419')
@section('heading', 'Your session expired')
@section('message', 'For your security, your session timed out due to inactivity. Please sign in again to continue.')
@section('icon')
    <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
@endsection

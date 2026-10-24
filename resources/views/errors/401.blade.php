@extends('errors.minimal')

@section('title', 'Unauthorized')
@section('code', 'Error 401')
@section('heading', 'Sign in required')
@section('message', 'You need to sign in to view this page. Please log in and try again.')
@section('icon')
    <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0m8 0v1a4 4 0 01-8 0v-1m8 0H8m9 9H7a2 2 0 01-2-2v-5a2 2 0 012-2h10a2 2 0 012 2v5a2 2 0 01-2 2z"/>
    </svg>
@endsection

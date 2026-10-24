@extends('errors.minimal')

@section('title', 'Page Not Found')
@section('code', 'Error 404')
@section('heading', 'Page not found')
@section('message', "The page you're looking for doesn't exist or may have been moved.")
@section('icon')
    <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
@endsection

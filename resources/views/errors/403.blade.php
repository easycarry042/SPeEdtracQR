@extends('errors.minimal')

@section('title', 'Access Denied')
@section('code', 'Error 403')
@section('heading', 'Access denied')
@section('message', $exception->getMessage() ?: "You don't have permission to view this page. If you believe this is a mistake, contact your administrator.")
@section('icon')
    <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
    </svg>
@endsection

@extends('layouts.inbox')

@section('title','Inbox')

@section('content')

<div class="row g-0 vh-100">

    <div class="col-lg-4">

        @include('conversations.partials.sidebar')

    </div>

    <div class="col-lg-8 d-flex justify-content-center align-items-center">

        <div class="text-center text-secondary">

            <i class="bi bi-chat-square-text display-2"></i>

            <h4 class="mt-3">

                Select a conversation

            </h4>

        </div>

    </div>

</div>

@endsection
@extends('layouts.inbox')

@section('title', $conversation->customer_name)

@section('content')

<div class="row g-0 vh-100">

    {{-- Left Sidebar --}}
    <div class="col-lg-4 border-end">

        @include('conversations.partials.sidebar')

    </div>

    {{-- Right Chat --}}
    <div class="col-lg-8 d-flex flex-column">

        @include('conversations.partials.chat-header')

        @include('conversations.partials.messages')

        @include('conversations.partials.composer')

    </div>

</div>

@endsection
<!doctype html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Messenger</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>

        body{

            background:#f3f4f6;

        }

        .sidebar{

            height:100vh;

            overflow:auto;

            background:white;

            border-right:1px solid #ddd;

        }

        .chat{

            height:100vh;

            background:#fafafa;

        }

        .conversation{

            padding:15px;

            border-bottom:1px solid #eee;

            text-decoration:none;

            color:#000;

            display:block;

        }

        .conversation:hover{

            background:#f7f7f7;

        }

        .conversation.active{

            background:#e9ecef;

        }

        .last{

            color:#777;

            font-size:13px;

        }

    </style>

</head>

<body>

<div class="container-fluid">

<div class="row">

<div class="col-md-3 sidebar">

<h4 class="p-3">

Messenger

</h4>

@forelse($conversations as $item)

<a

href="{{ route('messenger.show',$item) }}"

class="conversation

@if(isset($conversation) && $conversation->id==$item->id)

active

@endif

">

<div>

<strong>

{{ $item->customer_name ?: 'Facebook User' }}

</strong>

</div>

<div class="last">

{{ Str::limit($item->last_message,35) }}

</div>

</a>

@empty

<div class="p-3">

No Conversation

</div>

@endforelse

</div>

<div class="col-md-9 chat">

@if(isset($conversation))

<div class="p-3 border-bottom bg-white">

<h4>

{{ $conversation->customer_name ?: 'Facebook User' }}

</h4>

</div>

<div class="p-4">

@foreach($messages as $message)

<div class="mb-3">

@if($message->sender_type=='user')

<div class="alert alert-light d-inline-block">

{{ $message->message }}

</div>

@else

<div class="text-end">

<div class="alert alert-primary d-inline-block">

{{ $message->message }}

</div>

</div>

@endif

</div>

@endforeach


<div class="border-top bg-white p-3">

    @if(session('success'))

        <div class="alert alert-success">

            {{ session('success') }}

        </div>

    @endif

    <form
        action="{{ route('messenger.reply',$conversation) }}"
        method="POST"
    >

        @csrf

        <div class="input-group">

            <textarea

                name="message"

                rows="2"

                class="form-control"

                placeholder="Type your message..."

            ></textarea>

            <button
                class="btn btn-primary"
            >
                Send
            </button>

        </div>

        @error('message')

        <div class="text-danger mt-2">

            {{ $message }}

        </div>

        @enderror

    </form>

</div>

</div>

@else

<div

class="d-flex justify-content-center align-items-center"

style="height:100vh"

>

<h4 class="text-secondary">

Select a conversation

</h4>

</div>

@endif

</div>

</div>

</div>

</body>

</html>
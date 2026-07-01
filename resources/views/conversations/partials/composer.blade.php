<div class="chat-composer">

    <form method="POST"
          action="{{ url('/conversations/'.$conversation->id.'/reply') }}">

        @csrf

        <div class="input-group">

            <input
                type="text"
                name="message"
                class="form-control"
                placeholder="Type your message..."
                autocomplete="off"
                required>

            <button
                class="btn btn-primary">

                <i class="bi bi-send-fill"></i>

            </button>

        </div>

        @error('message')

            <small class="text-danger">

                {{ $message }}

            </small>

        @enderror

    </form>

</div>
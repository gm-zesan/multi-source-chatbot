<form
    action="{{ url('/conversations/'.$conversation->id.'/reply') }}"
    method="POST"
    class="composer">

    @csrf

    <div class="composer-box">

        <button id="emoji-btn" type="button" class="composer-icon">

            <i class="bi bi-emoji-smile"></i>

        </button>

        <textarea
            id="message"
            name="message"
            rows="1"
            placeholder="Type your message..."
            required></textarea>

        <button
            type="submit"
            class="send-btn">

            <i class="bi bi-send-fill"></i>

            <span>Send</span>

        </button>

    </div>

</form>
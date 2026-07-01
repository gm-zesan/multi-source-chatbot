<div class="sidebar-wrapper">

    <div class="sidebar-header">

        <h4 class="mb-3">

            Inbox

        </h4>

        <input type="text" class="form-control" placeholder="Search conversation...">

    </div>

    <div class="sidebar-filter mt-3">

        <button class="btn btn-primary btn-sm">

            All

        </button>

        <button class="btn btn-light btn-sm">

            Unread

        </button>

        <button class="btn btn-light btn-sm">

            Closed

        </button>

    </div>

    <div class="conversation-list mt-3">

        @include('conversations.partials.conversation-list')

    </div>

</div>

@extends('layouts.app')

@section('style')
    <style>
        main {
            margin-top: 50px;
        }

        #video-container {
            width: 700px;
            height: 500px;
            max-width: 90vw;
            max-height: 50vh;
            margin: 0 auto;
            border: 1px solid #099dfd;
            position: relative;
            box-shadow: 1px 1px 11px #9e9e9e;
        }

        #local-video {
            width: 30%;
            height: 30%;
            position: absolute;
            left: 10px;
            bottom: 10px;
            border: 1px solid #fff;
            border-radius: 6px;
            z-index: 2;
            cursor: pointer;
        }

        #remote-video {
            width: 100%;
            height: 100%;
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            top: 0;
            z-index: 1;
            margin: 0;
            padding: 0;
            cursor: pointer;
        }

        .action-btns {
            position: absolute;
            bottom: 20px;
            left: 50%;
            margin-left: -50px;
            z-index: 3;
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
        }
    </style>
@endsection

@section('content')
    <div class="container">
        <div class="d-flex gap-3">
            @foreach ($users as $user)
                <button class="btn btn-info text-white btn-sm" id="showOnline{{ $user->id }}"
                    onclick='placeCall("{{ $user->id }}", "{{ $user->name }}")'>
                    {{ $user->name }}
                </button>
            @endforeach
        </div>

        <!-- Incoming Call  -->
        <div id="incomingCallContainer"></div>

        <!-- End of Incoming Call  -->

    </div>

    <div id="video-container1"></div>
@endsection


@section('scripts')
    <script>
        let allUsers = @json($users);
        let authuserid = "{{ auth()->id() }}";
        let authuser = "{{ auth()->user()->name }}";
        let agora_id = "{{ env('AGORA_APP_ID') }}";

        let callPlaced = false;
        let client = null;
        let localStream = null;
        let mutedAudio = false;
        let mutedVideo = false;
        let userOnlineChannel = null;
        let onlineUsers = [];
        let incomingCall = false;
        let incomingCaller = "";
        let agoraChannel = null;


        initUserOnlineChannel();
        initUserOnlineListeners();

        function initUserOnlineChannel() {
            userOnlineChannel = window.Echo.join("agora-online-channel");
        }

        function initUserOnlineListeners() {
            userOnlineChannel.here((users) => {
                onlineUsers = users;
                users.forEach(user => {
                    allUsers.forEach(allUser => {
                        if (user.name == allUser.name) {
                            let onlinStatus = document.getElementById(`showOnline${user.id}`);
                            let spanEl = document.createElement('span');
                            spanEl.classList.add('text-success');
                            let node = document.createTextNode('Online');
                            spanEl.appendChild(node);
                            onlinStatus.appendChild(spanEl);
                        }
                    })
                });
            });

            userOnlineChannel.joining((user) => {
                // check user availability  => check online users to call
                const joiningUserIndex = onlineUsers.findIndex(
                    (data) => data.id === user.id
                );
                if (joiningUserIndex < 0) {
                    onlineUsers.push(user);
                }
            });

            userOnlineChannel.leaving((user) => {
                const leavingUserIndex = onlineUsers.findIndex(
                    (data) => data.id === user.id
                );
                onlineUsers.splice(leavingUserIndex, 1);
                //document.getElementById(`showOnline${user.id}`).firstElementChild.textContent = ""
            });
            //------------------------------//
            // listen to incomming call
            userOnlineChannel.listen("MakeAgoraCall", ({
                data
            }) => {
                console.log("Listening data.............", data);
                if (parseInt(data.userToCall) === parseInt(authuserid)) {
                    const callerIndex = onlineUsers.findIndex((user) => user.id === data.from);
                    incomingCaller = onlineUsers[callerIndex]["name"];
                    incomingCall = true;
                    // the channel that was sent over to the user being called is what
                    // the receiver will use to join the call when accepting the call.
                    agoraChannel = data.channelName;
                    if (incomingCall) {
                        document.getElementById('incomingCallContainer').innerHTML += `<div class="row my-5">
                                        <div class="col-12">
                                            <p>
                                                Incoming Call From <strong>${incomingCaller}</strong>
                                            </p>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-danger" data-dismiss="modal" onclick="declineCall()">
                                                    Decline
                                                </button>
                                                <button type="button" class="btn btn-success ml-5" onclick="acceptCall()">
                                                    Accept
                                                </button>
                                            </div>
                                        </div>
                                    </div>`;
                    }
                }
            });
        }

        // to show online offline status
        function getUserOnlineStatus(id) {
            const onlineUserIndex = onlineUsers.findIndex(
                (data) => data.id === id
            );
            if (onlineUserIndex < 0) {
                return "Offline";
            }
            return "Online";
        }


        async function placeCall(id, calleeName) {
            try {
                // channelName = the caller's and the callee's id. you can use anything. tho.
                const channelName = `${authuser}_${calleeName}`; ////////////////////////channel name/////////////
                // const channelName = `101`;

                const tokenRes = await generateToken(channelName);
                // Broadcasts a call event to the callee and also gets back the token
                await axios.post("/agora/call-user2", {
                    user_to_call: id,
                    username: authuser,
                    channel_name: channelName,
                });
                initializeAgora();
                joinRoom(tokenRes.data, channelName);
            } catch (error) {
                console.log(error);
            }
        }

        async function acceptCall() {
            initializeAgora();
            const tokenRes = await generateToken(agoraChannel);
            joinRoom(tokenRes.data, agoraChannel);
            incomingCall = false;
            callPlaced = true;

            document.getElementById('incomingCallContainer').innerHTML = "";
        }

        function declineCall() {
            // You can send a request to the caller to
            // alert them of rejected call
            incomingCall = false;
            document.getElementById('incomingCallContainer').innerHTML = "";
        }

        function generateToken(channelName) {
            return axios.post("/agora/token2", {
                channelName,
            });
        }

        function initializeAgora() {
            client = AgoraRTC.createClient({
                mode: "rtc",
                codec: "h264"
            });
            client.init(
                agora_id,
                () => {
                    console.log("AgoraRTC client initialized");
                },
                (err) => {
                    console.log("AgoraRTC client init failed", err);
                }
            );
        }

        async function joinRoom(token, channel) {
            client.join(
                token,
                channel,
                authuser,
                (uid) => {
                    console.log("User " + uid + " join channel successfully");
                    callPlaced = true;
                    if (callPlaced) {
                        document.getElementById('video-container1').innerHTML += `
                        <section id="video-container">
                                 <div id="local-video"></div>
                                 <div id="remote-video"></div>

                                <div class="action-btns">
                                    <button type="button" class="btn btn-info" onclick="handleAudioToggle(this)">
                                        Mute Audio
                                    </button>
                                    <button type="button" class="btn btn-primary mx-4" onclick="handleVideoToggle(this)">
                                        Mute Video
                                    </button>
                                    <button type="button" class="btn btn-danger" onclick="endCall()">
                                        End Call
                                    </button>
                                </div>
                        </section>`

                    }
                    createLocalStream();
                    initializedAgoraListeners();
                },
                (err) => {
                    console.log("Join channel failed", err);
                }
            );
        }

        function initializedAgoraListeners() {
            //Register event listeners
            client.on("stream-published", function(evt) {
                console.log("Publish local stream successfully");
                console.log(evt);
            });
            //subscribe remote stream
            client.on("stream-added", ({
                stream
            }) => {
                console.log("New stream added: " + stream.getId());
                client.subscribe(stream, function(err) {
                    console.log("Subscribe stream failed", err);
                });
            });
            client.on("stream-subscribed", (evt) => {
                //Attach remote stream to the remote-video div
                evt.stream.play("remote-video");
                client.publish(evt.stream);
            });
            client.on("stream-removed", ({
                stream
            }) => {
                console.log(String(stream.getId()));
                stream.close();
            });
            client.on("peer-online", (evt) => {
                console.log("peer-online", evt.uid);
            });
            client.on("peer-leave", (evt) => {
                var uid = evt.uid;
                var reason = evt.reason;
                console.log("remote user left ", uid, "reason: ", reason);
            });
            client.on("stream-unpublished", (evt) => {
                console.log(evt);
            });
        }

        function createLocalStream() {
            localStream = AgoraRTC.createStream({
                audio: true,
                video: true,
            });
            // Initialize the local stream
            localStream.init(
                () => {
                    // Play the local stream
                    localStream.play("local-video");
                    // Publish the local stream
                    client.publish(localStream, (err) => {
                        console.log("publish local stream", err);
                    });
                },
                (err) => {
                    console.log(err);
                }
            );
        }

        function endCall() {
            localStream.close();
            client.leave(
                () => {
                    console.log("Leave channel successfully");
                    callPlaced = false;
                },
                (err) => {
                    console.log("Leave channel failed");
                }
            );
            document.getElementById('video-container1').innerHTML = "";
        }

        function handleAudioToggle(e) {
            if (mutedAudio) {
                localStream.unmuteAudio();
                mutedAudio = false;
                e.innerText = "Mute Audio";
            } else {
                localStream.muteAudio();
                mutedAudio = true;
                e.innerText = "Unmute Audio";
            }
        }

        function handleVideoToggle(e) {
            if (mutedVideo) {
                localStream.unmuteVideo();
                mutedVideo = false;
                e.innerText = "Mute Video"
            } else {
                localStream.muteVideo();
                mutedVideo = true;
                e.innerText = "Unmute Audio"
            }
        }
    </script>
@endsection

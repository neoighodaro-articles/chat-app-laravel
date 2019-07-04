<template>
  <div class="card-body">
    <div id="chatbox" v-if="users">
        <dl v-for="message in messages" :key="message.id">
            <dt v-if="message.id"><i> ({{ formatTime(message.timestamp) }}) </i> : <strong>{{ findSender(message.senderId).name }}</strong></dt>
            <dd>{{ message.text }}</dd>
        </dl>
    </div>
    <hr>
    <div class="input-group">
        <input type="text" v-model="message" @keyup.enter="sendMessage" class="form-control" placeholder="Type your message..." autofocus>
        <div class="input-group-append">
          <button @click="sendMessage" class="btn btn-primary">Send</button>
        </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import moment from 'moment';
import Chatkit from '@pusher/chatkit-client';
export default {
    props: {
        roomId: String, 
        userId: String,
        initialMessages: Array,
    },
    data () {
        return {
            currentUser: null,
            message: '',
            messages: this.initialMessages,
            users: null,
        }
    },
    methods: {
        connectToChatkit() {
            const tokenProvider = new Chatkit.TokenProvider({
                url: `${process.env.MIX_APP_URL}/api/authenticate`,
            });
            const chatManager = new Chatkit.ChatManager({
                instanceLocator: process.env.MIX_CHATKIT_INSTANCE_LOCATOR,
                userId: this.userId,
                tokenProvider,
            });
            chatManager.connect()
                .then(user => {
                    this.currentUser = user;
                    this.subscribeToRoom();
                    console.log('Connected Successfully')
                })
                .catch(error => {
                        console.log('Error on connection', error)
                })
        },
        subscribeToRoom() {
            this.currentUser.subscribeToRoomMultipart({
                roomId: this.roomId,
                hooks: {
                    onMessage: message => {
                        this.messages.push({
                            id: message.id,
                            senderId: message.senderId,
                            text: message['parts'][0]['payload']['content'],
                            timestamp: message.createdAt
                        })
                    },
                    onUserJoined: async user => {
                        await this.getUsers()
                        this.messages.push({
                            text: `${user.name} joined ${this.formatTime(user.created_at)}`
                        })
                    },
                },
                messageLimit: 0
            })
        },
        getUsers() {
            axios.get(`${process.env.MIX_APP_URL}/api/users`)
                .then(res => {
                    this.users = res['data']['body']
                }); 
        },
        sendMessage() {
            if (this.message.trim() === '') return;
            axios.post( `${process.env.MIX_APP_URL}/api/message`, {
                user: this.userId,
                message: this.message
            })
            .then(message => {
                this.message = ''
            });
        },
        findSender(senderId){
            const sender = this.users.find(user => senderId == user.id);
            return sender
        },
        formatTime(timestamp) {
           return moment(timestamp).fromNow();
        },
    },
    created () {
        this.getUsers();
        this.connectToChatkit();
    },
};
</script>
<style>
#chatbox {
    text-align: left;
    max-height: 400px;
    overflow-y: scroll;
}
</style>
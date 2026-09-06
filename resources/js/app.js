import { createApp } from 'vue'

import '../css/app.css'

const csrf = document.querySelector('meta[name="csrf-token"]').content

const request = async (url, options = {}) => {
    const response = await fetch(url, {
        ...options,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers || {}) },
    })
    const data = await response.json()
    if (!response.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Request failed')
    return data
}

const app = createApp({
    data: () => ({ user: null, conversations: [], active: null, content: '', authMode: 'login', form: { name: '', email: '', password: '', password_confirmation: '' }, error: '', loading: false }),
    async mounted() {
        try { this.user = (await request('/api/me')).user; await this.loadConversations() } catch (_) { this.user = null }
    },
    methods: {
        async authenticate() {
            this.error = ''; this.loading = true
            try { const data = await request(`/auth/${this.authMode}`, { method: 'POST', body: JSON.stringify(this.form) }); this.user = data.user; await this.loadConversations() } catch (error) { this.error = error.message } finally { this.loading = false }
        },
        async loadConversations() { this.conversations = (await request('/api/conversations')).conversations; this.active = this.conversations[0] || (await this.createConversation()) },
        async createConversation() { const conversation = (await request('/api/conversations', { method: 'POST', body: '{}' })).conversation; this.conversations.unshift(conversation); this.active = conversation; return conversation },
        async send() { if (!this.content.trim() || this.loading) return; const content = this.content; this.content = ''; this.loading = true; this.active.messages.push({ role: 'user', content }); try { const data = await request(`/api/conversations/${this.active.id}/messages`, { method: 'POST', body: JSON.stringify({ content }) }); this.active.messages.push(data.message) } catch (error) { this.error = error.message } finally { this.loading = false }
        },
        async logout() { await request('/auth/logout', { method: 'POST', body: '{}' }); this.user = null; this.conversations = []; this.active = null },
    },
    template: `
      <main class="min-h-screen bg-[#101a1d] text-slate-100"><div v-if="!user" class="mx-auto flex min-h-screen max-w-md items-center px-6"><form @submit.prevent="authenticate" class="w-full space-y-5 rounded-3xl border border-white/10 bg-[#172326] p-8 shadow-2xl"><p class="text-sm font-semibold uppercase tracking-[.3em] text-cyan-300">Open AI Workspace</p><h1 class="font-serif text-4xl text-white">Think in public.</h1><div v-if="authMode === 'register'"><label class="text-sm text-slate-300">Name</label><input v-model="form.name" required class="mt-2 w-full rounded-xl bg-white/10 p-3 outline-none ring-cyan-300 focus:ring-2"></div><div><label class="text-sm text-slate-300">Email</label><input v-model="form.email" type="email" required class="mt-2 w-full rounded-xl bg-white/10 p-3 outline-none ring-cyan-300 focus:ring-2"></div><div><label class="text-sm text-slate-300">Password</label><input v-model="form.password" type="password" required class="mt-2 w-full rounded-xl bg-white/10 p-3 outline-none ring-cyan-300 focus:ring-2"></div><div v-if="authMode === 'register'"><label class="text-sm text-slate-300">Confirm password</label><input v-model="form.password_confirmation" type="password" required class="mt-2 w-full rounded-xl bg-white/10 p-3 outline-none ring-cyan-300 focus:ring-2"></div><p v-if="error" class="text-sm text-rose-300">{{ error }}</p><button class="w-full rounded-xl bg-cyan-300 p-3 font-semibold text-slate-950">{{ loading ? 'Working...' : authMode === 'login' ? 'Enter workspace' : 'Create workspace' }}</button><button type="button" @click="authMode = authMode === 'login' ? 'register' : 'login'; error = ''" class="w-full text-sm text-slate-400">{{ authMode === 'login' ? 'Create an account' : 'Back to login' }}</button></form></div><div v-else class="mx-auto flex min-h-screen max-w-6xl flex-col px-5 py-6 md:px-8"><header class="flex items-center justify-between border-b border-white/10 pb-5"><div><p class="text-xs uppercase tracking-[.3em] text-cyan-300">Workspace</p><h1 class="font-serif text-3xl">Good thinking, {{ user.name.split(' ')[0] }}.</h1></div><button @click="logout" class="text-sm text-slate-400 hover:text-white">Sign out</button></header><div class="grid flex-1 gap-5 py-5 md:grid-cols-[220px_1fr]"><aside class="space-y-3"><button @click="createConversation" class="w-full rounded-xl bg-cyan-300 px-4 py-3 text-sm font-semibold text-slate-950">+ New conversation</button><button v-for="conversation in conversations" @click="active = conversation" class="block w-full truncate rounded-xl px-4 py-3 text-left text-sm" :class="active?.id === conversation.id ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5'">{{ conversation.title }}</button></aside><section v-if="active" class="flex min-h-[65vh] flex-col rounded-3xl border border-white/10 bg-[#172326] p-5"><div class="flex-1 space-y-5 overflow-y-auto pb-5"><div v-if="!active.messages.length" class="flex h-full min-h-64 items-center justify-center text-center text-slate-400"><p class="max-w-sm text-lg">Start with a question, a rough idea, or a problem worth untangling.</p></div><div v-for="message in active.messages" class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'"><p class="max-w-[80%] rounded-2xl px-4 py-3" :class="message.role === 'user' ? 'bg-cyan-300 text-slate-950' : 'bg-white/10 text-slate-200'">{{ message.content }}</p></div></div><form @submit.prevent="send" class="flex gap-3 border-t border-white/10 pt-4"><input v-model="content" placeholder="Ask Gemini..." class="min-w-0 flex-1 rounded-xl bg-white/10 p-3 outline-none ring-cyan-300 focus:ring-2"><button class="rounded-xl bg-cyan-300 px-5 font-semibold text-slate-950">Send</button></form><p v-if="error" class="pt-2 text-sm text-rose-300">{{ error }}</p></section></div></div></main>
    `,
})

app.mount('#app')

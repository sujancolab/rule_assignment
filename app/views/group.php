<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8" />
    <title>Rule Assignment</title>
    <script src="https://unpkg.com/vue@3"></script>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 20px;
        }

        .controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .tree {
            margin-top: 20px;
        }

        .node {
            margin: 6px 0;
        }

        .node>.meta {
            font-size: 0.9em;
            color: #333;
        }

        .node button {
            margin-left: 8px;
        }

        .groups {
            margin-bottom: 12px;
        }
    </style>
</head>

<body>

    <div id="app">

        <h2>Rule Assignment</h2>

        <div class="groups">
            <label>Groups: </label>
            <select v-model="selectedGroup" @change="onGroupChange">
                <option v-for="g in groups" :value="g.id">{{ g.id }} - {{ g.name }}</option>
            </select>

            <input v-model="newGroupName" placeholder="New group name">
            <button @click="createGroup">Create Group</button>
        </div>

        <div class="controls">
            <input v-model.number="form.group_id" placeholder="Group ID" style="width:90px;" />
            <input v-model.number="form.rule_id" placeholder="Rule ID" style="width:120px;" />
            <input v-model="form.parent_id" placeholder="Parent ID (optional)" style="width:140px;" />
            <button @click="submit">Assign Rule</button>
            <span style="color:#666;font-size:0.9em;margin-left:8px">Click a node to set as parent</span>
        </div>

        <hr />

        <h3>Hierarchy for Group {{ selectedGroup }}</h3>

        <div class="tree">
            <ul>
                <tree-node v-for="n in tree" :key="n.id" :node="n" @set-parent="setParent" @delete-node="deleteNode" @edit-node="editNode"></tree-node>
            </ul>
        </div>

    </div>

    <script>
        window.CSRF_TOKEN = "<?= $_SESSION['csrf_token'] ?>";

        const app = Vue.createApp({
            data() {
                return {
                    groups: [],
                    selectedGroup: null,
                    newGroupName: '',
                    tree: [],
                    form: {
                        group_id: 1,
                        rule_id: null,
                        parent_id: null
                    }
                }
            },

            mounted() {
                this.loadGroups();
            },

            methods: {
                async loadGroups() {
                    const res = await fetch('?action=list_groups');
                    this.groups = await res.json();
                    if (this.groups.length && !this.selectedGroup) {
                        this.selectedGroup = this.groups[0].id;
                        this.form.group_id = this.selectedGroup;
                        this.loadTree();
                    }
                },

                async createGroup() {
                    if (!this.newGroupName.trim()) return alert('Please enter a group name');

                    const res = await fetch('?action=create_group', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': window.CSRF_TOKEN
                        },
                        body: JSON.stringify({
                            name: this.newGroupName
                        })
                    });

                    if (!res.ok) {
                        const err = await res.json();
                        return alert(err.error || 'Failed');
                    }

                    const g = await res.json();
                    this.groups.unshift(g);
                    this.selectedGroup = g.id;
                    this.form.group_id = g.id;
                    this.newGroupName = '';
                    this.loadTree();
                },

                onGroupChange() {
                    this.form.group_id = this.selectedGroup;
                    this.loadTree();
                },

                async submit() {
                    if (!this.form.group_id || !this.form.rule_id) return alert('Group and Rule are required');

                    const payload = {
                        ...this.form
                    };

                    const res = await fetch('?action=store', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': window.CSRF_TOKEN,
                            'Idempotency-Key': crypto.randomUUID()
                        },
                        body: JSON.stringify(payload)
                    });

                    if (!res.ok) {
                        const err = await res.json();
                        return alert(err.error || 'Failed to assign');
                    }

                    this.form.rule_id = null;
                    this.form.parent_id = null;
                    this.loadTree();
                },

                async loadTree() {
                    if (!this.form.group_id) return;
                    const res = await fetch(`?action=tree&group_id=${this.form.group_id}`);
                    this.tree = await res.json();
                },

                setParent(id) {
                    this.form.parent_id = id;
                },

                async deleteNode(id) {
                    if (!confirm('Delete this assignment and its children?')) return;

                    const res = await fetch('?action=delete_assignment', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': window.CSRF_TOKEN
                        },
                        body: JSON.stringify({
                            id
                        })
                    });

                    if (!res.ok) {
                        const err = await res.json();
                        return alert(err.error || 'Delete failed');
                    }

                    this.loadTree();
                },

                async editNode(id) {
                    const parent = prompt('Enter new parent assignment id (empty for root):');
                    const parent_id = parent === '' ? null : (parent ? parseInt(parent) : null);

                    const res = await fetch('?action=update_assignment', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': window.CSRF_TOKEN
                        },
                        body: JSON.stringify({
                            id,
                            parent_id
                        })
                    });

                    if (!res.ok) {
                        const err = await res.json();
                        return alert(err.error || 'Update failed');
                    }

                    this.loadTree();
                }
            }
        });

        app.component('tree-node', {
            props: ['node'],
            emits: ['set-parent', 'delete-node', 'edit-node'],
            template: `
        <li class="node">
            <div @click="$emit('set-parent', node.id)" style="cursor:pointer">
                <strong>Assignment #{{ node.id }}</strong>
                <span class="meta"> - Rule {{ node.rule_id }} - created: {{ node.created_at }}</span>
                <button @click.stop="$emit('edit-node', node.id)">Move</button>
                <button @click.stop="$emit('delete-node', node.id)">Delete</button>
            </div>
            <ul v-if="node.children && node.children.length">
                <tree-node v-for="c in node.children" :key="c.id" :node="c" @set-parent="$emit('set-parent', $event)" @delete-node="$emit('delete-node', $event)" @edit-node="$emit('edit-node', $event)"></tree-node>
            </ul>
        </li>
    `
        });

        app.mount('#app');
    </script>

</body>

</html>
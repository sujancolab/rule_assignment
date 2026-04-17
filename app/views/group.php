<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8" />
    <title>Rule Assignment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <script src="https://unpkg.com/vue@3"></script>
</head>

<body class="bg-light">
    <div id="app" class="container py-3">
        <div class="row align-items-center mb-2">
            <div class="col-auto">
                <label class="form-label small mb-1">Rule name</label>
                <input class="form-control form-control-sm" v-model="newRuleName" placeholder="Rule name" />
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Type</label>
                <select class="form-select form-select-sm" v-model="newRuleType">
                    <option value="CONDITION">CONDITION</option>
                    <option value="DECISION">DECISION</option>
                </select>
            </div>
            <div class="col-auto mt-4">
                <button class="btn btn-primary btn-sm" @click="createRule">Create Rule</button>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">New group</label>
                <div class="input-group input-group-sm">
                    <input class="form-control form-control-sm" v-model="newGroupName" placeholder="New group name" />
                    <button class="btn btn-outline-primary btn-sm" type="button" @click="createGroup">Create Group</button>
                </div>
            </div>
        </div>

        <!-- Assignment form -->
        <div class="row align-items-center mb-3 g-2">
            <div class="col-auto">
                <select class="form-select form-select-sm" v-model.number="form.group_id" @change="onGroupChange">
                    <option :value="null">-- Select group --</option>
                    <option v-for="g in groups" :value="g.id">{{ g.name }}</option>
                </select>
            </div>
            <div class="col-auto" style="min-width:260px;">
                <select class="form-select form-select-sm" v-model.number="form.rule_id">
                    <option :value="null">-- Select rule --</option>
                    <option v-for="r in rules" :value="r.id">{{ r.name }} <span v-if="r.type">({{ r.type }})</span></option>
                </select>
            </div>
            <div class="col-auto" style="min-width:260px;">
                <select class="form-select form-select-sm" v-model.number="form.parent_id">
                    <option :value="null">-- Root (no parent) --</option>
                    <option v-for="opt in parentOptions" :value="opt.id">{{ opt.label }}</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-success btn-sm" @click="submit">Assign Rule</button>
            </div>
        </div>

        <!-- Hierarchy title -->
        <h6 class="mb-2">Hierarchy for Group <span class="fw-bold" v-if="selectedGroup">{{ selectedGroupName }}</span></h6>

        <!-- Assignment list-->
        <div class="card">
            <div class="card-body p-2">
                <ul class="list-group">
                    <template v-if="tree.length">
                        <tree-node v-for="n in tree" :key="n.id" :node="n" @edit-node="editNode" @delete-node="deleteNode"></tree-node>
                    </template>
                    <li v-else class="list-group-item text-muted">No assignments yet for this group.</li>
                </ul>
            </div>
        </div>
        <!-- Move modal  -->
        <div v-if="showMoveModal">
            <div class="modal fade show d-block" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Move: {{ moveTargetLabel }}</h5>
                            <button type="button" class="btn-close" @click="showMoveModal=false"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label small">Select new parent</label>
                            <select class="form-select" v-model.number="moveSelectedParent">
                                <option :value="null">-- Root (no parent) --</option>
                                <option v-for="o in moveModalOptions" :value="o.id">{{ o.label }}</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary btn-sm" @click="showMoveModal=false">Cancel</button>
                            <button class="btn btn-primary btn-sm" @click="confirmMove">Save</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop fade show"></div>
        </div>

    </div>

    <script>
        window.CSRF_TOKEN = "<?= $_SESSION['csrf_token'] ?>";

        const app = Vue.createApp({
            data() {
                return {
                    groups: [],
                    rules: [],
                    rulesMap: {},
                    selectedGroup: null,
                    newGroupName: '',
                    newRuleName: '',
                    newRuleType: 'CONDITION',
                    tree: [],
                    form: {
                        group_id: null,
                        rule_id: null,
                        parent_id: null
                    },
                    parentOptions: [],
                    showMoveModal: false,
                    moveTarget: null,
                    moveSelectedParent: null,
                    moveModalOptions: []
                }
            },
            mounted() {
                this.loadGroups();
                this.loadRules();
            },
            computed: {
                selectedGroupName() {
                    const g = this.groups.find(x => x.id === this.selectedGroup);
                    return g ? g.name : (this.selectedGroup || '');
                },
                moveTargetLabel() {
                    if (!this.moveTarget) return '';
                    const n = this.findNodeById(this.moveTarget);
                    if (!n) return '';
                    return `${n.rule_name ? n.rule_name : 'Rule ' + n.rule_id}${n.rule_type ? ' (' + n.rule_type + ')' : ''}`;
                }
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
                    // when user changes the group select, update the selectedGroup and reload the tree
                    this.selectedGroup = this.form.group_id;
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
                async loadRules() {
                    const res = await fetch('?action=list_rules');
                    this.rules = await res.json();
                    this.rulesMap = {};
                    for (const r of this.rules) this.rulesMap[r.id] = r;
                },
                async createRule() {
                    if (!this.newRuleName.trim()) return alert('Enter rule name');
                    const res = await fetch('?action=create_rule', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': window.CSRF_TOKEN
                        },
                        body: JSON.stringify({
                            name: this.newRuleName,
                            type: this.newRuleType
                        })
                    });
                    if (!res.ok) {
                        const err = await res.json();
                        return alert(err.error || 'Create failed');
                    }
                    const r = await res.json();
                    this.rules.unshift(r);
                    this.rulesMap[r.id] = r;
                    this.newRuleName = '';
                    this.newRuleType = 'CONDITION';
                },
                async loadTree() {
                    if (!this.form.group_id) return;
                    const res = await fetch(`?action=tree&group_id=${this.form.group_id}`);
                    this.tree = await res.json();
                    const decorate = (nodes) => {
                        for (const n of nodes) {
                            const meta = this.rulesMap[n.rule_id];
                            n.rule_name = meta ? meta.name : null;
                            n.rule_type = meta ? meta.type : null;
                            if (n.children && n.children.length) decorate(n.children);
                        }
                    };
                    decorate(this.tree);
                    this.buildParentOptions();
                },
                buildParentOptions() {
                    const out = [];
                    const walk = (nodes, depth = 0) => {
                        for (const n of nodes) {
                            const indent = Array(depth).fill('\u2014 ').join('');
                            const label = `${indent}${n.rule_name ? n.rule_name : 'Rule ' + n.rule_id}${n.rule_type ? ' (' + n.rule_type + ')' : ''}`;
                            out.push({
                                id: n.id,
                                label,
                                rule_type: n.rule_type
                            });
                            if (n.children && n.children.length) walk(n.children, depth + 1);
                        }
                    };
                    walk(this.tree, 0);
                    this.parentOptions = out;
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
                    this.openMoveModal(id);
                },
                openMoveModal(id) {
                    this.moveTarget = id;
                    this.moveSelectedParent = null;
                    const forbidden = this.getSubtreeIds(id);
                    const opts = [{
                        id: null,
                        label: '-- Root (no parent) --'
                    }];
                    for (const o of this.parentOptions) {
                        if (o.id === id) continue;
                        if (forbidden.includes(o.id)) continue;
                        if (o.rule_type === 'DECISION') continue;
                        opts.push(o);
                    }
                    this.moveModalOptions = opts;
                    this.showMoveModal = true;
                },
                getSubtreeIds(id) {
                    const out = [];
                    const find = (nodes) => {
                        for (const n of nodes) {
                            if (n.id === id) {
                                const collect = (m) => {
                                    out.push(m.id);
                                    if (m.children)
                                        for (const c of m.children) collect(c);
                                };
                                collect(n);
                                return true;
                            }
                            if (n.children && n.children.length) {
                                if (find(n.children)) return true;
                            }
                        }
                        return false;
                    };
                    find(this.tree);
                    return out;
                },
                async confirmMove() {
                    const payload = {
                        id: this.moveTarget,
                        parent_id: this.moveSelectedParent === null ? null : this.moveSelectedParent
                    };
                    const res = await fetch('?action=update_assignment', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': window.CSRF_TOKEN
                        },
                        body: JSON.stringify(payload)
                    });
                    if (!res.ok) {
                        const err = await res.json();
                        return alert(err.error || 'Update failed');
                    }
                    this.showMoveModal = false;
                    this.moveTarget = null;
                    this.moveSelectedParent = null;
                    this.moveModalOptions = [];
                    this.loadTree();
                },
                findNodeById(id) {
                    let found = null;
                    const walk = (nodes) => {
                        for (const n of nodes) {
                            if (n.id === id) {
                                found = n;
                                return true;
                            }
                            if (n.children && n.children.length) {
                                if (walk(n.children)) return true;
                            }
                        }
                        return false;
                    };
                    walk(this.tree);
                    return found;
                }
            }
        });

        app.component('tree-node', {
            props: ['node'],
            emits: ['set-parent', 'delete-node', 'edit-node'],
            template: `
        <li class="list-group-item">
          <div class="d-flex align-items-center">
                        <div>
                            <strong>{{ node.rule_name ? node.rule_name : ('Rule ' + node.rule_id) }}</strong>
                            <div class="small text-muted">{{ node.rule_type ? ('(' + node.rule_type + ')') : '' }}</div>
                            <div class="small text-muted">created: {{ node.created_at }}</div>
                        </div>
            <div class="ms-auto d-flex gap-2">
              <button class="btn btn-sm btn-outline-secondary" @click.stop="$emit('edit-node', node.id)">Move</button>
              <button class="btn btn-sm btn-outline-secondary" @click.stop="$emit('delete-node', node.id)">Delete</button>
            </div>
          </div>
          <ul v-if="node.children && node.children.length" class="list-group ms-3 mt-2">
            <tree-node v-for="c in node.children" :key="c.id" :node="c" @set-parent="$emit('set-parent', $event)" @delete-node="$emit('delete-node', $event)" @edit-node="$emit('edit-node', $event)"></tree-node>
          </ul>
        </li>
      `
        });

        app.mount('#app');
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
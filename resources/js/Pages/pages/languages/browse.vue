<script>
import { Link, Head, useForm } from '@inertiajs/vue3';
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Swal from "sweetalert2";
import { ref, computed, watch } from "vue";
import axios from "axios";
import { debounce } from 'lodash';
import { integer } from '@vuelidate/validators';
import { useI18n } from 'vue-i18n';

const TRANSLATION_GROUPS = [
  { value: 'pages_names', labelKey: 'pages_names', kind: 'admin' },
  { value: 'view_pages_1', labelKey: 'view_pages_1', kind: 'admin' },
  { value: 'view_pages_2', labelKey: 'view_pages_2', kind: 'admin' },
  { value: 'view_pages_3', labelKey: 'view_pages_3', kind: 'admin' },
  { value: 'error_messages', labelKey: 'error_messages', kind: 'admin' },
  { value: 'success_messages', labelKey: 'success_messages', kind: 'admin' },
  { value: 'push_notifications', labelKey: 'push_notifications', kind: 'admin' },
  { value: 'wallet_remarks', labelKey: 'wallet_remarks', kind: 'admin' },
  { value: 'invoice_translation', labelKey: 'invoice_translation', kind: 'admin' },
  { value: 'user_app', labelKey: 'user_app', kind: 'mobile' },
  { value: 'driver_app', labelKey: 'driver_app', kind: 'mobile' },
];

export default {
  components: {
    Layout,
    PageHeader,
    Head,
    Link,
  },
  props: {
    successMessage: String,
    alertMessage: String,
    language_id: integer,
    app_for: String,
    language_name: String,
    language_code: String,
    results: {
      type: Array,
      required: false,
      default: () => [],
    },
  },
  setup(props) {
    const { t } = useI18n();
    const languageName = props.language_name;
    const languageCode = props.language_code || 'xx';
    const results = ref([]);
    const searchTerm = ref('');
    const debouncedSearch = ref('');
    const filterMode = ref('all');
    const searchAllGroups = ref(false);
    const crossGroupResults = ref([]);
    const crossGroupLoading = ref(false);
    const crossGroupTruncated = ref(false);
    const highlightKey = ref('');
    const loading = ref(false);
    const showAutoTranslateAll = ref(false);
    const successMessage = ref(props.successMessage || '');
    const alertMessage = ref(props.alertMessage || '');

    const form = useForm({
      group: '',
      all_value: '',
      translated_keyword: '',
    });

    const autoTranslateRef = useForm({
      key_value: '',
      index: '',
      group: '',
      current_value: '',
      translated_value: '',
    });

    const applyDebouncedSearch = debounce((value) => {
      debouncedSearch.value = value;
    }, 250);

    watch(searchTerm, (value) => {
      applyDebouncedSearch(value);
    });

    watch([debouncedSearch, searchAllGroups], () => {
      if (searchAllGroups.value) {
        runCrossGroupSearch();
      } else {
        crossGroupResults.value = [];
        crossGroupTruncated.value = false;
      }
    });

    const dismissMessage = () => {
      successMessage.value = '';
      alertMessage.value = '';
    };

    const normalize = (value) => String(value ?? '').toLowerCase();

    const isMissing = (row) => {
      const translated = row?.translated_value;
      const english = row?.current_value;
      return translated === '' || translated == null || String(translated) === String(english);
    };

    const isMobileGroup = computed(
      () => form.group === 'user_app' || form.group === 'driver_app',
    );

    const selectedGroupMeta = computed(
      () => TRANSLATION_GROUPS.find((g) => g.value === form.group) || null,
    );

    const arbFileName = computed(() => `intl_${languageCode}.arb`);

    const filteredResults = computed(() => {
      let rows = results.value || [];
      if (filterMode.value === 'missing') {
        rows = rows.filter(isMissing);
      }
      const q = normalize(debouncedSearch.value).trim();
      if (!q || searchAllGroups.value) {
        return rows;
      }
      return rows.filter((row) => {
        return (
          normalize(row.key_value).includes(q) ||
          normalize(row.current_value).includes(q) ||
          normalize(row.translated_value).includes(q)
        );
      });
    });

    const matchSummary = computed(() => {
      const total = (results.value || []).length;
      const shown = filteredResults.value.length;
      if (!form.group) return '';
      if (searchAllGroups.value) return `${total} keys loaded in group`;
      if (!debouncedSearch.value.trim() && filterMode.value === 'all') {
        return `${total} keys`;
      }
      return `${shown} of ${total} shown`;
    });

    const findRowIndex = (key_value) =>
      results.value.findIndex((row) => row.key_value === key_value);

    const escapeHtml = (text) =>
      String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const highlightMatch = (text) => {
      const raw = String(text ?? '');
      const q = debouncedSearch.value.trim();
      if (!q || searchAllGroups.value) {
        return escapeHtml(raw);
      }
      const escaped = escapeHtml(raw);
      const needle = escapeHtml(q).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      try {
        return escaped.replace(new RegExp(`(${needle})`, 'gi'), '<mark class="lang-hit">$1</mark>');
      } catch (_) {
        return escaped;
      }
    };

    const handleGroupChange = async (event) => {
      const selectedGroup = event.target.value;
      highlightKey.value = '';
      await fetchDatas(selectedGroup);
    };

    const fetchDatas = async (param = 'pages_names') => {
      try {
        const params = form.data();
        params.group = param;
        form.group = param;

        const response = await axios.get(`/languages/load-translation/${props.language_id}`, { params });
        results.value = response.data.data || [];
        showAutoTranslateAll.value = true;
      } catch (error) {
        console.error(t('error_fetching_languages'), error);
      }
    };

    const runCrossGroupSearch = async () => {
      const q = debouncedSearch.value.trim();
      if (q.length < 2) {
        crossGroupResults.value = [];
        crossGroupTruncated.value = false;
        return;
      }
      crossGroupLoading.value = true;
      try {
        const response = await axios.get(`/languages/search-translation/${props.language_id}`, {
          params: { q, limit: 100 },
        });
        crossGroupResults.value = response.data.data || [];
        crossGroupTruncated.value = !!response.data.truncated;
      } catch (error) {
        console.error('Cross-group search failed', error);
        crossGroupResults.value = [];
      } finally {
        crossGroupLoading.value = false;
      }
    };

    const openCrossGroupMatch = async (match) => {
      searchAllGroups.value = false;
      form.group = match.group;
      highlightKey.value = match.key_value;
      searchTerm.value = match.key_value;
      debouncedSearch.value = match.key_value;
      await fetchDatas(match.group);
      setTimeout(() => {
        const el = document.getElementById(`lang-row-${match.key_value}`);
        if (el) {
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }, 120);
    };

    const autoTranslateAll = async () => {
      if (!form.group) return;
      form.all_value = true;
      loading.value = true;
      try {
        const response = await axios.post(`/languages/auto-translate-all/${props.language_id}`, form.data());
        results.value = response.data.data;
        successMessage.value = t('auto_translated_successfully');
      } catch (error) {
        if (error.response?.status === 500) {
          alertMessage.value = error.message;
        }
      } finally {
        loading.value = false;
      }
    };

    const autoTranslate = async (key_value, current_value) => {
      const index = findRowIndex(key_value);
      if (index < 0) return;

      autoTranslateRef.key_value = key_value;
      autoTranslateRef.index = index;
      autoTranslateRef.group = form.group;
      autoTranslateRef.current_value = current_value;

      try {
        const response = await axios.post(
          `/languages/auto-translate/${props.language_id}`,
          autoTranslateRef.data(),
        );
        results.value[index].translated_value = response.data.data;
        successMessage.value = t('auto_translated_successfully');
      } catch (error) {
        console.error(t('error_translating_text'), error);
        if (error.response && error.response.status === 500) {
          alertMessage.value = error.message;
          Swal.fire({
            icon: 'error',
            title: 'Cloud Translation API Error',
            html: `
              <p>Enable Cloud Translation API first:</p>
              <p><a href="https://console.developers.google.com/apis/api/translate.googleapis.com/" target="_blank">Google API Console</a></p>
              <p>Also ensure your map key is set in Map Settings.</p>
            `,
          });
        } else {
          alertMessage.value = t('an_error_occurred_while_translating_the_text');
        }
      }
    };

    const updateData = async (key_value, translated_value) => {
      const index = findRowIndex(key_value);
      if (index < 0) return;

      autoTranslateRef.key_value = key_value;
      autoTranslateRef.index = index;
      autoTranslateRef.group = form.group;
      autoTranslateRef.translated_value = translated_value;
      autoTranslateRef.current_value = translated_value;

      try {
        const response = await axios.post(
          `/languages/translate/update/${props.language_id}`,
          autoTranslateRef.data(),
        );
        results.value[index].translated_value = response.data.data;
        successMessage.value = t('translation_updated_successfully');
      } catch (error) {
        console.error(t('error_translating_text'), error);
        if (error.response && error.response.status === 500) {
          alertMessage.value = error.message;
        } else {
          alertMessage.value = t('an_error_occurred_while_updating_translation');
        }
      }
    };

    const copyKey = async (key) => {
      try {
        await navigator.clipboard.writeText(key);
        successMessage.value = `Copied: ${key}`;
      } catch (_) {
        alertMessage.value = 'Unable to copy key';
      }
    };

    const clearSearch = () => {
      searchTerm.value = '';
      debouncedSearch.value = '';
      highlightKey.value = '';
      crossGroupResults.value = [];
    };

    const downloadFile = async () => {
      try {
        const params = form.data();
        params.group = form.group;
        const response = await axios.get(`/languages/download-translation/${props.language_id}`, {
          params,
          responseType: 'blob',
        });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const fileName =
          form.group === 'user_app' || form.group === 'driver_app'
            ? `${form.group}.arb`
            : `${form.group}.json`;
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', fileName);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        if (isMobileGroup.value) {
          successMessage.value = `Downloaded ${fileName}. Rename to ${arbFileName.value}, place in the Flutter app, then rebuild.`;
        }
      } catch (error) {
        console.error(t('error_downloading_file'), error);
      }
    };

    return {
      form,
      results,
      filteredResults,
      searchTerm,
      debouncedSearch,
      filterMode,
      searchAllGroups,
      crossGroupResults,
      crossGroupLoading,
      crossGroupTruncated,
      highlightKey,
      matchSummary,
      successMessage,
      alertMessage,
      dismissMessage,
      fetchDatas,
      handleGroupChange,
      autoTranslate,
      autoTranslateAll,
      updateData,
      loading,
      showAutoTranslateAll,
      downloadFile,
      languageName,
      languageCode,
      arbFileName,
      isMobileGroup,
      selectedGroupMeta,
      highlightMatch,
      openCrossGroupMatch,
      clearSearch,
      copyKey,
      isMissing,
      TRANSLATION_GROUPS,
    };
  },
};
</script>

<template>
  <Layout>
    <Head :title="`Translations — ${languageName}`" />
    <PageHeader :title="languageName" :pageTitle="$t('languages')" pageLink="/languages" />

    <!-- How it works (static) -->
    <div class="lang-guide mb-3">
      <div class="lang-guide__header">
        <div>
          <h5 class="mb-1">How translations work</h5>
          <p class="text-muted small mb-0">
            Admin panel &amp; website strings update after save. Mobile app strings need a file replace + app rebuild.
          </p>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-lg-4">
          <div class="lang-guide__card">
            <div class="lang-guide__step">1</div>
            <h6 class="mb-2">Find &amp; edit here</h6>
            <ol class="small text-muted mb-0 ps-3">
              <li>Choose a <strong>group</strong> (or use Search all groups).</li>
              <li>Search by <strong>keyword</strong> or English word.</li>
              <li>Edit the translated text and click <strong>Update</strong>.</li>
            </ol>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="lang-guide__card lang-guide__card--admin">
            <div class="lang-guide__step">2</div>
            <h6 class="mb-2">Admin / website groups</h6>
            <p class="small text-muted mb-2">
              Groups like Pages, View Pages, Errors, Push notifications, Wallet remarks, Invoice.
            </p>
            <p class="small mb-0">
              <span class="badge bg-success-subtle text-success">Live after save</span>
              Changes apply to the admin panel / web without rebuilding mobile apps.
            </p>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="lang-guide__card lang-guide__card--mobile">
            <div class="lang-guide__step">3</div>
            <h6 class="mb-2">Mobile apps (User App / Driver App)</h6>
            <p class="small mb-2">
              <span class="badge bg-warning-subtle text-warning">Not live on phones until rebuild</span>
            </p>
            <p class="small text-muted mb-0">
              Editing here only updates server files. You must download the <code>.arb</code>, replace it in the Flutter project, generate l10n, then rebuild &amp; release the apps.
            </p>
          </div>
        </div>
      </div>

      <div class="lang-guide__mobile mt-3">
        <h6 class="mb-2">
          <i class="ri-smartphone-line me-1"></i>
          Mobile app translation steps
          <span class="text-muted fw-normal">(from Flutter Translation Setup)</span>
        </h6>
        <ol class="small mb-3 ps-3">
          <li>
            Select group <strong>User App</strong> or <strong>Driver App</strong>, edit words, click <strong>Update</strong> (or Auto Translate).
          </li>
          <li>
            Click <strong>Download Translations</strong> — you get a <code>.arb</code> file (e.g. <code>user_app.arb</code> / <code>driver_app.arb</code>).
          </li>
          <li>
            Rename the file to <code>intl_(language_code).arb</code>.
            Example for Arabic: <code>intl_ar.arb</code>.
            For this language use: <code>{{ arbFileName }}</code>
          </li>
          <li>
            Place the file in the Flutter project at
            <code>project/lib/l10/</code>
            (User app project for User App group; Driver app project for Driver App group).
          </li>
          <li>
            In the project terminal run:
            <code class="lang-guide__code">flutter gen-l10n</code>
          </li>
          <li>
            If this is a <strong>new</strong> language, also add it to
            <code>project/lib/common/app_constants.dart</code> inside <code>languageList</code>
            (name + language code).
          </li>
          <li>
            <strong>Rebuild and publish</strong> the Android / iOS apps. Users only see the new words after they install the updated app build.
          </li>
        </ol>
        <div class="alert alert-warning border-0 small mb-0 py-2">
          <strong>Important:</strong> Saving User App / Driver App translations on this page does
          <em>not</em> change text inside already-installed mobile apps. Always download → replace in code →
          <code>flutter gen-l10n</code> → rebuild.
        </div>
      </div>

      <div class="lang-guide__tips mt-3 small text-muted">
        <strong>Google Auto Translate:</strong>
        Needs a valid map key in
        <a href="/map-setting">Map Settings</a>
        and
        <a href="https://console.developers.google.com/apis/api/translate.googleapis.com" target="_blank" rel="noopener">Cloud Translation API</a>
        enabled.
      </div>
    </div>

    <!-- Toolbar + table -->
    <BRow>
      <BCol lg="12">
        <BCard no-body class="lang-workspace">
          <BCardHeader class="border-0 pb-0">
            <BRow class="g-3 align-items-end">
              <BCol md="3">
                <label for="select_languages" class="form-label">{{ $t('select_group') }}</label>
                <select
                  id="select_languages"
                  class="form-select"
                  v-model="form.group"
                  @change="handleGroupChange"
                >
                  <option disabled value="">{{ $t('choose_group') }}</option>
                  <optgroup label="Admin / website">
                    <option
                      v-for="group in TRANSLATION_GROUPS.filter((g) => g.kind === 'admin')"
                      :key="group.value"
                      :value="group.value"
                    >
                      {{ $t(group.labelKey) }}
                    </option>
                  </optgroup>
                  <optgroup label="Mobile apps (requires rebuild)">
                    <option
                      v-for="group in TRANSLATION_GROUPS.filter((g) => g.kind === 'mobile')"
                      :key="group.value"
                      :value="group.value"
                    >
                      {{ $t(group.labelKey) }}
                    </option>
                  </optgroup>
                </select>
              </BCol>

              <BCol md="4">
                <label class="form-label">Search keyword or text</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="ri-search-line"></i></span>
                  <input
                    v-model="searchTerm"
                    type="search"
                    class="form-control"
                    placeholder="e.g. ride_otp or Cancel ride"
                  />
                  <button
                    v-if="searchTerm"
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="clearSearch"
                  >
                    Clear
                  </button>
                </div>
                <div class="form-check mt-2 mb-0">
                  <input
                    id="searchAllGroups"
                    v-model="searchAllGroups"
                    class="form-check-input"
                    type="checkbox"
                  />
                  <label class="form-check-label" for="searchAllGroups">
                    Search all groups
                  </label>
                </div>
              </BCol>

              <BCol md="2">
                <label class="form-label">Filter</label>
                <select v-model="filterMode" class="form-select" :disabled="searchAllGroups">
                  <option value="all">All keys</option>
                  <option value="missing">Missing only</option>
                </select>
              </BCol>

              <BCol md="auto" class="ms-md-auto">
                <div class="d-flex flex-wrap gap-2">
                  <BButton
                    variant="success"
                    @click="downloadFile"
                    :disabled="!form.group"
                  >
                    <i class="ri-download-line align-bottom me-1"></i>
                    {{ $t('download_translations') }}
                  </BButton>
                  <BButton
                    v-if="showAutoTranslateAll"
                    variant="primary"
                    @click="autoTranslateAll"
                    :disabled="loading || app_for === 'demo' || !form.group"
                  >
                    <span v-if="loading">
                      <i class="ri-loader-4-line align-bottom me-1"></i> {{ $t('loading') }}
                    </span>
                    <span v-else>
                      <i class="ri-translate-2 align-bottom me-1"></i>
                      {{ $t('auto_translate_all') }}
                    </span>
                  </BButton>
                </div>
              </BCol>
            </BRow>

            <div
              v-if="isMobileGroup && !searchAllGroups"
              class="alert alert-warning mt-3 mb-0 py-2 small"
            >
              <i class="ri-error-warning-line me-1"></i>
              You are editing
              <strong>{{ $t(selectedGroupMeta?.labelKey || form.group) }}</strong>
              for language <strong>{{ languageName }}</strong> (<code>{{ languageCode }}</code>).
              After saving, download the file, rename to <code>{{ arbFileName }}</code>,
              put it in <code>lib/l10/</code> of the Flutter project, run
              <code>flutter gen-l10n</code>, then <strong>rebuild the app</strong>.
            </div>
          </BCardHeader>

          <!-- Cross-group results -->
          <BCardBody v-if="searchAllGroups">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="small text-muted">
                <span v-if="crossGroupLoading">Searching all groups…</span>
                <span v-else-if="debouncedSearch.trim().length < 2">Type at least 2 characters to search</span>
                <span v-else>
                  {{ crossGroupResults.length }} matches
                  <span v-if="crossGroupTruncated">· showing first 100 — refine your query</span>
                </span>
              </div>
            </div>
            <div class="table-responsive lang-table-wrap">
              <table class="table align-middle table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Group</th>
                    <th>Keyword</th>
                    <th>English</th>
                    <th>Translated</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody v-if="crossGroupResults.length">
                  <tr v-for="(match, idx) in crossGroupResults" :key="`${match.group}-${match.key_value}-${idx}`">
                    <td>
                      <code class="small">{{ match.group }}</code>
                      <div v-if="match.group === 'user_app' || match.group === 'driver_app'" class="small text-warning">
                        mobile · rebuild needed
                      </div>
                    </td>
                    <td>
                      <code class="small" v-html="highlightMatch(match.key_value)"></code>
                      <span v-if="match.is_missing" class="badge bg-warning-subtle text-warning ms-1">missing</span>
                    </td>
                    <td class="lang-ellipsis" v-b-tooltip.hover :title="match.current_value">
                      <span v-html="highlightMatch(match.current_value)"></span>
                    </td>
                    <td class="lang-ellipsis" v-b-tooltip.hover :title="match.translated_value">
                      <span v-html="highlightMatch(match.translated_value)"></span>
                    </td>
                    <td class="text-end">
                      <BButton size="sm" variant="outline-primary" @click="openCrossGroupMatch(match)">
                        Open &amp; edit
                      </BButton>
                    </td>
                  </tr>
                </tbody>
                <tbody v-else-if="!crossGroupLoading && debouncedSearch.trim().length >= 2">
                  <tr>
                    <td colspan="5" class="text-center text-muted py-5">No matches across groups</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>

          <!-- Within-group table -->
          <BCardBody v-else>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="small text-muted">{{ matchSummary || 'Select a group to load keys' }}</div>
            </div>
            <div class="table-responsive lang-table-wrap">
              <table class="table align-middle table-hover mb-0">
                <thead class="table-light sticky-top">
                  <tr>
                    <th style="min-width: 160px">Keyword</th>
                    <th style="min-width: 180px">English</th>
                    <th style="min-width: 220px">{{ $t('translated_word') }}</th>
                    <th style="width: 140px">{{ $t('google_auto_translate') }}</th>
                    <th style="width: 110px">{{ $t('action') }}</th>
                  </tr>
                </thead>
                <tbody v-if="filteredResults.length > 0">
                  <tr
                    v-for="result in filteredResults"
                    :id="`lang-row-${result.key_value}`"
                    :key="result.key_value"
                    :class="{ 'table-warning': highlightKey === result.key_value }"
                  >
                    <td>
                      <div class="d-flex align-items-start gap-1">
                        <code class="small text-break" v-html="highlightMatch(result.key_value)"></code>
                        <button
                          type="button"
                          class="btn btn-sm btn-link p-0 text-muted"
                          title="Copy key"
                          @click="copyKey(result.key_value)"
                        >
                          <i class="ri-file-copy-line"></i>
                        </button>
                      </div>
                      <span v-if="isMissing(result)" class="badge bg-warning-subtle text-warning mt-1">missing</span>
                    </td>
                    <td class="lang-ellipsis" v-b-tooltip.hover :title="result.current_value">
                      <span v-html="highlightMatch(result.current_value)"></span>
                    </td>
                    <td>
                      <input
                        type="text"
                        class="form-control form-control-sm"
                        v-model="result.translated_value"
                        @keydown.enter.prevent="updateData(result.key_value, result.translated_value)"
                      />
                    </td>
                    <td>
                      <BButton
                        size="sm"
                        variant="outline-primary"
                        @click.prevent="autoTranslate(result.key_value, result.current_value)"
                        :disabled="app_for === 'demo' || languageName == 'English'"
                      >
                        {{ $t('auto_translate') }}
                      </BButton>
                    </td>
                    <td>
                      <BButton
                        size="sm"
                        variant="primary"
                        @click.prevent="updateData(result.key_value, result.translated_value)"
                        :disabled="app_for === 'demo'"
                      >
                        {{ $t('update') }}
                      </BButton>
                    </td>
                  </tr>
                </tbody>
                <tbody v-else>
                  <tr>
                    <td colspan="5" class="text-center py-5">
                      <div class="text-muted">
                        <i class="ri-translate-2 display-6 d-block mb-2 opacity-50"></i>
                        <h5 class="mb-1">
                          {{
                            form.group
                              ? (debouncedSearch || filterMode === 'missing'
                                ? 'No matching translations'
                                : $t('no_data_found'))
                              : 'Select a group to browse translations'
                          }}
                        </h5>
                        <p class="small mb-0" v-if="!form.group">
                          Tip: turn on “Search all groups” if you don’t know which file contains the word.
                        </p>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <div>
      <div
        v-if="successMessage"
        class="custom-alert alert alert-success alert-border-left fade show"
      >
        <div class="alert-content">
          <i class="ri-checkbox-circle-line me-2 align-middle"></i>
          <strong>Success</strong> — {{ successMessage }}
          <button type="button" class="btn-close btn-close-success" @click="dismissMessage"></button>
        </div>
      </div>

      <div
        v-if="alertMessage"
        class="custom-alert alert alert-danger alert-border-left fade show"
      >
        <div class="alert-content">
          <i class="ri-error-warning-line me-2 align-middle"></i>
          <strong>Alert</strong> — {{ alertMessage }}
          <button type="button" class="btn-close btn-close-danger" @click="dismissMessage"></button>
        </div>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
.lang-guide {
  background: #fff;
  border: 1px solid #e9ecef;
  border-radius: 0.75rem;
  padding: 1.25rem 1.5rem;
}

.lang-guide__header h5 {
  font-weight: 700;
}

.lang-guide__card {
  height: 100%;
  background: #f8f9fa;
  border: 1px solid #eef1f4;
  border-radius: 0.65rem;
  padding: 1rem 1.1rem;
  position: relative;
}

.lang-guide__card--admin {
  background: #f3faf6;
  border-color: #d7efe1;
}

.lang-guide__card--mobile {
  background: #fff8e8;
  border-color: #f3dfb0;
}

.lang-guide__step {
  width: 1.6rem;
  height: 1.6rem;
  border-radius: 50%;
  background: #212529;
  color: #fff;
  font-size: 0.75rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.5rem;
}

.lang-guide__mobile {
  background: #fffaf0;
  border: 1px dashed #efc56d;
  border-radius: 0.65rem;
  padding: 1rem 1.15rem;
}

.lang-guide__code {
  display: inline-block;
  background: #212529;
  color: #f8f9fa;
  padding: 0.15rem 0.45rem;
  border-radius: 0.25rem;
  margin-top: 0.15rem;
}

.lang-workspace {
  border-radius: 0.75rem;
  overflow: hidden;
}

.lang-table-wrap {
  max-height: min(70vh, 720px);
  overflow: auto;
}

.lang-table-wrap thead.sticky-top th {
  z-index: 2;
  box-shadow: inset 0 -1px 0 #dee2e6;
}

.lang-ellipsis {
  max-width: 260px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

:deep(mark.lang-hit) {
  background: #ffe08a;
  padding: 0 0.1em;
  border-radius: 0.15rem;
}

.custom-alert {
  max-width: 640px;
  position: fixed;
  top: 90px;
  right: 20px;
  z-index: 1050;
}
</style>

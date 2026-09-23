const SELECTORS = {
    employeeSearch: '[data-employee-search]',
    employeeRole: '[data-employee-role-filter]',
    employeeGrade: '[data-employee-grade-filter]',
    employeeCard: '[data-employee-card], [data-employee-row]',
    employeeEmpty: '[data-employee-empty]',
    employeeCount: '[data-employee-count], [data-visible-count]',
    recommendations: '[data-recommendations], [data-recommendation-root]',
    recommendationForm: '[data-recommendations-form], [data-recommendation-form]',
    recommendationTrigger: '[data-recommendations-trigger], [data-recommendation-submit]',
    recommendationList: '[data-recommendations-list], [data-recommendation-results]',
    recommendationStatus: '[data-recommendations-status], [data-recommendation-status]',
    recommendationSkeleton: '[data-recommendations-skeleton], [data-recommendation-skeleton]',
    recommendationEmpty: '[data-recommendation-empty], [data-recommendations-empty]',
    completeForm: '[data-complete-form]',
    completeSelect: '[data-complete-event-select], [data-event-select]',
    completeTrigger: '[data-complete-trigger], [data-complete-submit]',
    completeStatus: '[data-complete-status]',
    completeResult: '[data-complete-result]',
    roleSwitch: '[data-role-switch]',
    uploadInput: '[data-upload-input], [data-file-input]',
    uploadField: '[data-upload-field], [data-file-field], [data-upload-dropzone], [data-file-dropzone]',
    uploadFilename: '[data-upload-filename], [data-file-name]',
    uploadDropzone: '[data-upload-dropzone], [data-file-dropzone]',
};

const recommendationRequests = new WeakMap();
const completionRequests = new WeakMap();

class ApiError extends Error {
    constructor(message, status, payload = null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.payload = payload;
    }
}

function normalise(value) {
    return String(value ?? '').trim().toLocaleLowerCase('ru-RU');
}

function csrfToken(scope = document) {
    return document.querySelector('meta[name="csrf-token"]')?.content
        || scope.querySelector?.('input[name="_token"]')?.value
        || '';
}

function createElement(tag, className, text) {
    const element = document.createElement(tag);

    if (className) {
        element.className = className;
    }

    if (text !== undefined && text !== null) {
        element.textContent = String(text);
    }

    return element;
}

function setHidden(element, isHidden) {
    if (!element) {
        return;
    }

    element.hidden = isHidden;
    element.classList.toggle('hidden', isHidden);
}

function setStatus(element, message = '', state = 'idle') {
    if (!element) {
        return;
    }

    element.textContent = message;
    element.dataset.state = state;
    setHidden(element, !message);
    element.setAttribute('aria-live', state === 'error' ? 'assertive' : 'polite');
    element.setAttribute('role', state === 'error' ? 'alert' : 'status');
}

function validationMessage(payload) {
    const errors = payload?.errors;

    if (!errors || typeof errors !== 'object') {
        return null;
    }

    const firstError = Object.values(errors).flat().find(Boolean);

    return firstError ? String(firstError) : null;
}

async function requestJson(url, data, { signal, scope = document } = {}) {
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const token = csrfToken(scope);

    if (token) {
        headers['X-CSRF-TOKEN'] = token;
    }

    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify(data ?? {}),
        signal,
    });
    const rawBody = await response.text();
    let payload = null;

    if (rawBody) {
        try {
            payload = JSON.parse(rawBody);
        } catch {
            payload = null;
        }
    }

    if (!response.ok) {
        const message = validationMessage(payload)
            || payload?.message
            || (response.status === 419 ? 'Сессия истекла. Обновите страницу и повторите действие.' : null)
            || (response.status === 501 ? 'Функция пока подключается. Попробуйте ещё раз немного позже.' : null)
            || `Не удалось выполнить запрос (${response.status}).`;

        throw new ApiError(message, response.status, payload);
    }

    if (!payload || typeof payload !== 'object') {
        throw new ApiError('Сервер вернул ответ в неожиданном формате.', response.status);
    }

    return payload;
}

function initEmployeeFilters() {
    const search = document.querySelector(SELECTORS.employeeSearch);
    const role = document.querySelector(SELECTORS.employeeRole);
    const grade = document.querySelector(SELECTORS.employeeGrade);
    const cards = Array.from(document.querySelectorAll(SELECTORS.employeeCard));

    if (!cards.length || (!search && !role && !grade)) {
        return;
    }

    const emptyState = document.querySelector(SELECTORS.employeeEmpty);
    const count = document.querySelector(SELECTORS.employeeCount);

    const filter = () => {
        const query = normalise(search?.value);
        const selectedRole = normalise(role?.value);
        const selectedGrade = normalise(grade?.value);
        let visibleCount = 0;

        cards.forEach((card) => {
            const searchableText = normalise(card.dataset.search || `${card.dataset.name || ''} ${card.textContent || ''}`);
            const cardRole = normalise(card.dataset.role);
            const cardGrade = normalise(card.dataset.grade);
            const queryMatches = !query || searchableText.includes(query);
            const roleMatches = !selectedRole || selectedRole === 'all' || cardRole === selectedRole;
            const gradeMatches = !selectedGrade || selectedGrade === 'all' || cardGrade === selectedGrade;
            const isVisible = queryMatches && roleMatches && gradeMatches;

            card.hidden = !isVisible;
            card.setAttribute('aria-hidden', String(!isVisible));

            if (isVisible) {
                visibleCount += 1;
            }
        });

        if (emptyState) {
            setHidden(emptyState, visibleCount !== 0);
        }

        if (count) {
            const template = count.dataset.template || '{count}';
            count.textContent = template.replace('{count}', String(visibleCount));
            count.setAttribute('aria-live', 'polite');
        }

        document.dispatchEvent(new CustomEvent('careerquest:employees-filtered', {
            detail: { count: visibleCount, query, role: selectedRole, grade: selectedGrade },
        }));
    };

    search?.addEventListener('input', filter);
    role?.addEventListener('change', filter);
    grade?.addEventListener('change', filter);
    filter();
}

function buildRecommendationSkeleton() {
    const skeleton = createElement('div', 'recommendation-skeleton grid gap-4 lg:grid-cols-3');
    skeleton.dataset.generatedSkeleton = '';
    skeleton.setAttribute('aria-hidden', 'true');

    for (let index = 0; index < 3; index += 1) {
        const card = createElement('div', 'animate-pulse rounded-3xl border border-slate-200 bg-white p-5');
        card.append(
            createElement('div', 'mb-4 h-3 w-20 rounded-full bg-slate-200'),
            createElement('div', 'mb-3 h-6 w-4/5 rounded-full bg-slate-200'),
            createElement('div', 'mb-2 h-3 w-full rounded-full bg-slate-100'),
            createElement('div', 'h-3 w-2/3 rounded-full bg-slate-100'),
        );
        skeleton.append(card);
    }

    return skeleton;
}

function setRecommendationsLoading(root, isLoading) {
    const trigger = root.querySelector(SELECTORS.recommendationTrigger);
    const list = root.querySelector(SELECTORS.recommendationList);
    let skeleton = root.querySelector(SELECTORS.recommendationSkeleton)
        || root.querySelector('[data-generated-skeleton]');

    if (!skeleton && isLoading) {
        skeleton = buildRecommendationSkeleton();

        if (list) {
            list.before(skeleton);
        } else {
            root.append(skeleton);
        }
    }

    root.dataset.loading = String(isLoading);
    root.setAttribute('aria-busy', String(isLoading));

    if (trigger) {
        trigger.disabled = isLoading;
        trigger.setAttribute('aria-busy', String(isLoading));
    }

    if (skeleton) {
        setHidden(skeleton, !isLoading);
    }

    if (list) {
        setHidden(list, isLoading || (!isLoading && list.dataset.state === 'empty'));
        list.setAttribute('aria-busy', String(isLoading));
    }

    if (isLoading) {
        setHidden(root.querySelector(SELECTORS.recommendationEmpty), true);
    }
}

const factorLabels = {
    skill_gap: 'Закрывает разрыв',
    critical_skill: 'Критичный навык',
    career_goal: 'Карьерная цель',
    history_clean: 'Без пропусков',
    completion_history: 'Успешная история',
    timely_completion: 'Выполнено вовремя',
    upcoming_session: 'Ближайшая сессия',
    self_paced: 'Свободный темп',
    mandatory: 'Обязательно',
};

const eventTypeLabels = {
    course: 'Курс',
    workshop: 'Воркшоп',
    mentoring: 'Менторство',
    mentorship: 'Менторство',
    project: 'Проект',
    certification: 'Сертификация',
    conference: 'Конференция',
};

function humanise(value, labels = {}) {
    const key = String(value ?? '');

    return labels[key] || key.replaceAll('_', ' ').replace(/^./, (character) => character.toLocaleUpperCase('ru-RU'));
}

function completeEndpoint(root) {
    const employeeId = root.dataset.employeeId;

    return root.dataset.completeUrl
        || document.querySelector(SELECTORS.completeForm)?.action
        || (employeeId ? `/employees/${encodeURIComponent(employeeId)}/complete` : '');
}

function buildCompleteForm(root, recommendation) {
    const endpoint = completeEndpoint(root);

    if (!endpoint || !recommendation.event_id) {
        return null;
    }

    const form = createElement('form', 'recommendation-card__actions mt-5');
    form.method = 'post';
    form.action = endpoint;
    form.dataset.completeForm = '';

    const token = csrfToken(root);

    if (token) {
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = token;
        form.append(csrf);
    }

    const eventId = document.createElement('input');
    eventId.type = 'hidden';
    eventId.name = 'event_id';
    eventId.value = String(recommendation.event_id);

    const button = createElement(
        'button',
        'inline-flex w-full items-center justify-center rounded-2xl bg-emerald-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-wait disabled:opacity-60',
        'Отметить выполненной',
    );
    button.type = 'submit';
    button.dataset.completeTrigger = '';
    button.dataset.completeEvent = '';
    button.dataset.eventId = String(recommendation.event_id);

    form.append(eventId, button);

    return form;
}

function recommendationCard(root, recommendation, index) {
    const card = createElement('article', 'recommendation-card flex h-full flex-col rounded-3xl border border-slate-200 bg-white p-5 shadow-sm');
    const heading = createElement('div', 'mb-4 flex items-start justify-between gap-3');
    const rank = Number(recommendation.rank) || index + 1;
    const rankBadge = createElement('span', 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-900', `#${rank}`);
    const source = recommendation.source === 'llm' ? 'AI' : 'Резервный алгоритм';
    const sourceBadge = createElement(
        'span',
        recommendation.source === 'llm'
            ? 'rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-800'
            : 'rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900',
        source,
    );
    const title = createElement('h3', 'text-lg font-bold text-slate-950', recommendation.title || recommendation.event_id || 'Активность');
    const meta = createElement('div', 'mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-500');
    const type = createElement('span', '', humanise(recommendation.type || 'activity', eventTypeLabels));

    heading.append(rankBadge, sourceBadge);
    meta.append(type);

    if (Number.isFinite(Number(recommendation.score))) {
        meta.append(createElement('span', '', `Оценка ${new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 }).format(Number(recommendation.score))}`));
    }

    card.append(heading, title, meta);

    if (Array.isArray(recommendation.factors) && recommendation.factors.length) {
        const factors = createElement('div', 'mt-4 flex flex-wrap gap-2');

        recommendation.factors.forEach((factor) => {
            const badge = createElement('span', 'rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700', humanise(factor, factorLabels));
            badge.dataset.factor = String(factor);
            factors.append(badge);
        });

        card.append(factors);
    }

    if (recommendation.rationale) {
        card.append(createElement('p', 'mt-4 grow text-sm leading-6 text-slate-600', recommendation.rationale));
    }

    const form = buildCompleteForm(root, recommendation);

    if (form) {
        card.append(form);
    }

    card.dataset.recommendationCard = '';
    card.dataset.eventId = String(recommendation.event_id || '');

    return card;
}

function renderRecommendations(root, recommendations) {
    const list = root.querySelector(SELECTORS.recommendationList);
    const empty = root.querySelector(SELECTORS.recommendationEmpty);

    if (!list) {
        throw new Error('На странице не найден контейнер для рекомендаций.');
    }

    const fragment = document.createDocumentFragment();

    recommendations.forEach((recommendation, index) => {
        fragment.append(recommendationCard(root, recommendation, index));
    });

    list.replaceChildren(fragment);
    setHidden(list, recommendations.length === 0);
    setHidden(empty, recommendations.length !== 0);
    list.dataset.state = recommendations.length ? 'ready' : 'empty';
}

function recommendationErrorMessage(error) {
    if (error instanceof ApiError && error.status === 501) {
        return 'Сервис рекомендаций ещё подключается. Профиль доступен, попробуйте запросить рекомендации позже.';
    }

    if (error instanceof ApiError) {
        return error.message;
    }

    return 'Не удалось получить рекомендации. Проверьте соединение и попробуйте ещё раз.';
}

function recommendationMock(root) {
    const source = root.querySelector('[data-recommendation-mock]');

    if (!source) {
        return [];
    }

    try {
        const recommendations = JSON.parse(source.textContent || '[]');

        return Array.isArray(recommendations) ? recommendations.slice(0, 3) : [];
    } catch {
        return [];
    }
}

function initRecommendations() {
    const roots = new Set(document.querySelectorAll(SELECTORS.recommendations));

    document.querySelectorAll(SELECTORS.recommendationForm).forEach((form) => {
        roots.add(form.closest(`${SELECTORS.recommendations}, section`) || form.parentElement || form);
    });

    roots.forEach((root) => {
        const form = root.matches(SELECTORS.recommendationForm)
            ? root
            : root.querySelector(SELECTORS.recommendationForm);
        const trigger = root.matches(SELECTORS.recommendationTrigger)
            ? root
            : root.querySelector(SELECTORS.recommendationTrigger);
        let status = root.querySelector(SELECTORS.recommendationStatus);

        if (!form && !trigger) {
            return;
        }

        if (!status) {
            status = createElement('p', 'recommendation-status mt-3 text-sm');
            status.dataset.recommendationStatus = '';
            setHidden(status, true);
            form?.after(status);
        }

        const load = async (event) => {
            if (!window.fetch) {
                return;
            }

            event?.preventDefault();

            const employeeId = root.dataset.employeeId;
            const url = root.dataset.url
                || form?.action
                || (employeeId ? `/employees/${encodeURIComponent(employeeId)}/recommendations` : '');

            if (!url) {
                setStatus(status, 'Не указан адрес сервиса рекомендаций.', 'error');
                return;
            }

            recommendationRequests.get(root)?.abort();
            const controller = new AbortController();
            recommendationRequests.set(root, controller);
            setStatus(status);
            setRecommendationsLoading(root, true);

            try {
                const payload = await requestJson(url, {}, { signal: controller.signal, scope: form || root });
                const recommendations = Array.isArray(payload.recommendations) ? payload.recommendations.slice(0, 3) : null;

                if (!recommendations) {
                    throw new ApiError('Сервер вернул рекомендации в неожиданном формате.', 200, payload);
                }

                renderRecommendations(root, recommendations);
                setStatus(
                    status,
                    recommendations.length ? `Подобрано рекомендаций: ${recommendations.length}.` : 'Подходящих активностей пока нет.',
                    recommendations.length ? 'success' : 'empty',
                );

                root.dispatchEvent(new CustomEvent('careerquest:recommendations-loaded', {
                    bubbles: true,
                    detail: { recommendations },
                }));
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    const preview = error instanceof ApiError && error.status === 501
                        ? recommendationMock(root)
                        : [];

                    if (preview.length) {
                        renderRecommendations(root, preview);
                        setStatus(status, 'AI-слой ещё подключается — показан демонстрационный fallback на данных профиля.', 'preview');
                    } else {
                        setStatus(status, recommendationErrorMessage(error), 'error');
                    }
                }
            } finally {
                if (recommendationRequests.get(root) === controller) {
                    recommendationRequests.delete(root);
                    setRecommendationsLoading(root, false);
                }
            }
        };

        form?.addEventListener('submit', load);

        if (trigger && trigger.form !== form) {
            trigger.addEventListener('click', load);
        }
    });
}

function completionUi(form) {
    const localScope = form.closest('[data-completion], [data-recommendations], [data-recommendation-root], section') || form.parentElement || document;
    let status = localScope.querySelector?.(SELECTORS.completeStatus)
        || document.querySelector(SELECTORS.completeStatus);
    let result = localScope.querySelector?.(SELECTORS.completeResult)
        || document.querySelector(SELECTORS.completeResult);

    if (!status) {
        status = createElement('p', 'complete-status mt-3 text-sm');
        status.dataset.completeStatus = '';
        form.after(status);
    }

    if (!result) {
        result = createElement('section', 'complete-result mt-4 rounded-3xl border border-emerald-200 bg-emerald-50 p-5');
        result.dataset.completeResult = '';
        setHidden(result, true);
        status.after(result);
    }

    return { status, result };
}

function renderProgressResult(container, payload) {
    const deltas = Array.isArray(payload.skill_deltas) ? payload.skill_deltas : [];
    const readiness = payload.grade_readiness && typeof payload.grade_readiness === 'object'
        ? payload.grade_readiness
        : null;
    const fragment = document.createDocumentFragment();

    fragment.append(createElement('h3', 'text-lg font-bold text-emerald-950', 'Прогресс обновлён'));

    if (deltas.length) {
        const list = createElement('ul', 'mt-3 grid gap-2');

        deltas.forEach((delta) => {
            const skillName = delta.skill_name || delta.name || delta.skill_id || 'Навык';
            const item = createElement('li', 'flex items-center justify-between gap-4 rounded-2xl bg-white px-4 py-3 text-sm');
            item.append(
                createElement('span', 'font-medium text-slate-800', skillName),
                createElement('strong', 'text-emerald-800', `${delta.from ?? '—'} → ${delta.to ?? '—'}`),
            );
            list.append(item);
        });

        fragment.append(list);
    } else {
        fragment.append(createElement('p', 'mt-2 text-sm text-emerald-900', 'Активность завершена. Уровни навыков не изменились.'));
    }

    if (readiness) {
        const total = Number(readiness.total) || 0;
        const covered = Number(readiness.covered) || 0;
        const grade = readiness.grade || 'следующему грейду';
        const readinessBlock = createElement('div', 'mt-4');
        const readinessText = createElement('p', 'text-sm font-semibold text-emerald-950', `Готовность к ${grade}: ${covered} из ${total}`);
        const progress = document.createElement('progress');
        progress.className = 'mt-2 h-2 w-full overflow-hidden rounded-full';
        progress.max = Math.max(total, 1);
        progress.value = Math.min(covered, progress.max);
        progress.setAttribute('aria-label', `Готовность к ${grade}`);
        readinessBlock.append(readinessText, progress);
        fragment.append(readinessBlock);
    }

    container.replaceChildren(fragment);
    setHidden(container, false);
    container.setAttribute('role', 'status');
    container.setAttribute('tabindex', '-1');
    container.focus({ preventScroll: false });
}

function updateSkillState(deltas) {
    if (!Array.isArray(deltas)) {
        return;
    }

    const skillNodes = Array.from(document.querySelectorAll('[data-skill-id]'));

    deltas.forEach((delta) => {
        skillNodes
            .filter((node) => String(node.dataset.skillId) === String(delta.skill_id))
            .forEach((node) => {
                node.dataset.current = String(delta.to ?? '');

                node.querySelectorAll('[data-skill-current], [data-current-level], [data-skill-level]').forEach((value) => {
                    value.textContent = String(delta.to ?? '—');
                    value.dataset.value = String(delta.to ?? '');
                });

                node.querySelectorAll('[data-skill-progress]').forEach((progress) => {
                    const level = Math.min(5, Math.max(0, Number(delta.to) || 0));
                    progress.style.width = `${level * 20}%`;
                });
            });
    });
}

function updateReadinessState(readiness) {
    if (!readiness || typeof readiness !== 'object') {
        return;
    }

    const covered = Number(readiness.covered) || 0;
    const total = Number(readiness.total) || 0;
    const percent = total > 0 ? Math.round((covered / total) * 100) : 0;

    document.querySelectorAll('[data-readiness-covered]').forEach((element) => {
        element.textContent = String(covered);
    });
    document.querySelectorAll('[data-readiness-total]').forEach((element) => {
        element.textContent = String(total);
    });
    document.querySelectorAll('[data-readiness-grade]').forEach((element) => {
        element.textContent = String(readiness.grade || '');
    });
    document.querySelectorAll('[data-readiness-percent]').forEach((element) => {
        element.dataset.value = String(percent);

        if (element instanceof HTMLProgressElement) {
            element.max = 100;
            element.value = percent;
        } else if (element.dataset.progressBar !== undefined) {
            element.style.width = `${percent}%`;
        } else {
            element.textContent = `${percent}%`;
        }
    });
}

function markEventCompleted(eventId) {
    document.querySelectorAll('[data-event-id]').forEach((element) => {
        if (String(element.dataset.eventId) !== String(eventId)) {
            return;
        }

        const card = element.closest('[data-recommendation-card]');
        const button = element.matches('button') ? element : element.querySelector('button');

        if (card) {
            card.dataset.completed = 'true';
        }

        if (button) {
            button.disabled = true;
            button.textContent = 'Выполнено';
        }
    });

    document.querySelectorAll(SELECTORS.completeSelect).forEach((select) => {
        const completedWasSelected = select.value === String(eventId);

        Array.from(select.options).forEach((option) => {
            if (String(option.value) === String(eventId)) {
                option.disabled = true;

                if (!option.textContent.includes('выполнено')) {
                    option.textContent += ' — выполнено';
                }
            }
        });

        if (completedWasSelected) {
            const nextOption = Array.from(select.options).find((option) => !option.disabled);

            if (nextOption) {
                select.value = nextOption.value;
            }
        }
    });
}

function eventIdFromForm(form) {
    const data = new FormData(form);

    return data.get('event_id')
        || form.querySelector(SELECTORS.completeSelect)?.value
        || form.querySelector('[data-event-id]')?.dataset.eventId
        || '';
}

function setCompleteLoading(form, isLoading) {
    const trigger = form.querySelector(SELECTORS.completeTrigger)
        || form.querySelector('button[type="submit"], input[type="submit"]');
    const select = form.querySelector(SELECTORS.completeSelect);

    form.dataset.loading = String(isLoading);
    form.setAttribute('aria-busy', String(isLoading));

    if (trigger) {
        trigger.disabled = isLoading;
        trigger.setAttribute('aria-busy', String(isLoading));
    }

    if (select) {
        select.disabled = isLoading;
    }
}

function initCompletion() {
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest?.(SELECTORS.completeForm);

        if (!form || !window.fetch) {
            return;
        }

        event.preventDefault();

        const eventId = eventIdFromForm(form);
        const { status, result } = completionUi(form);

        if (!eventId) {
            setStatus(status, 'Выберите активность для завершения.', 'error');
            return;
        }

        const url = form.dataset.url || form.action;

        if (!url) {
            setStatus(status, 'Не указан адрес обновления прогресса.', 'error');
            return;
        }

        completionRequests.get(form)?.abort();
        const controller = new AbortController();
        completionRequests.set(form, controller);
        setStatus(status);
        setHidden(result, true);
        setCompleteLoading(form, true);

        try {
            const payload = await requestJson(url, { event_id: eventId }, { signal: controller.signal, scope: form });

            if (!Array.isArray(payload.skill_deltas) || !payload.grade_readiness) {
                throw new ApiError('Сервер вернул прогресс в неожиданном формате.', 200, payload);
            }

            renderProgressResult(result, payload);
            updateSkillState(payload.skill_deltas);
            updateReadinessState(payload.grade_readiness);
            markEventCompleted(eventId);
            setStatus(status, 'Активность отмечена выполненной.', 'success');

            document.dispatchEvent(new CustomEvent('careerquest:event-completed', {
                detail: { eventId, ...payload },
            }));
        } catch (error) {
            if (error?.name !== 'AbortError') {
                setStatus(
                    status,
                    error instanceof ApiError ? error.message : 'Не удалось обновить прогресс. Попробуйте ещё раз.',
                    'error',
                );
            }
        } finally {
            if (completionRequests.get(form) === controller) {
                completionRequests.delete(form);
                setCompleteLoading(form, false);
            }
        }
    });
}

function initRoleSwitch() {
    const handledForms = new WeakSet();

    document.querySelectorAll(SELECTORS.roleSwitch).forEach((hook) => {
        const form = hook instanceof HTMLFormElement ? hook : hook.closest('form');
        const control = hook instanceof HTMLSelectElement
            ? hook
            : form?.querySelector('select[name="role"], input[name="role"]');

        if (!form || !control || handledForms.has(form)) {
            return;
        }

        handledForms.add(form);

        const setLoading = () => {
            form.dataset.loading = 'true';
            form.setAttribute('aria-busy', 'true');
            control.setAttribute('aria-disabled', 'true');
            form.querySelectorAll('button, input[type="submit"]').forEach((button) => {
                button.disabled = true;
            });
        };

        control.addEventListener('change', () => {
            form.requestSubmit();
        });
        form.addEventListener('submit', setLoading);
    });
}

function filenameTarget(input, field) {
    if (input.dataset.filenameTarget) {
        try {
            return document.querySelector(input.dataset.filenameTarget);
        } catch {
            return null;
        }
    }

    return field?.querySelector(SELECTORS.uploadFilename)
        || input.parentElement?.querySelector(SELECTORS.uploadFilename);
}

function updateUploadField(input) {
    const field = input.closest(SELECTORS.uploadField) || input.parentElement;
    const target = filenameTarget(input, field);
    const files = Array.from(input.files || []);
    const hasFile = files.length > 0;

    if (target) {
        const fallback = target.dataset.emptyLabel || 'Файл не выбран';
        target.textContent = hasFile
            ? (files.length === 1 ? files[0].name : `${files[0].name} и ещё ${files.length - 1}`)
            : fallback;
        target.title = hasFile ? files.map((file) => file.name).join(', ') : '';
        target.setAttribute('aria-live', 'polite');
    }

    if (field) {
        field.dataset.hasFile = String(hasFile);
        field.classList.toggle('has-file', hasFile);
    }

    input.dispatchEvent(new CustomEvent('careerquest:file-selected', {
        bubbles: true,
        detail: { files },
    }));
}

function initUploads() {
    document.querySelectorAll(SELECTORS.uploadInput).forEach((input) => {
        input.addEventListener('change', () => updateUploadField(input));
        updateUploadField(input);
    });

    document.querySelectorAll(SELECTORS.uploadDropzone).forEach((dropzone) => {
        const input = dropzone.matches(SELECTORS.uploadInput)
            ? dropzone
            : dropzone.querySelector(SELECTORS.uploadInput);

        if (!input) {
            return;
        }

        let dragDepth = 0;
        const setDragging = (isDragging) => {
            dropzone.dataset.dragging = String(isDragging);
            dropzone.classList.toggle('is-dragging', isDragging);
        };

        dropzone.addEventListener('dragenter', (event) => {
            event.preventDefault();
            dragDepth += 1;
            setDragging(true);
        });
        dropzone.addEventListener('dragover', (event) => {
            event.preventDefault();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'copy';
            }
        });
        dropzone.addEventListener('dragleave', () => {
            dragDepth = Math.max(0, dragDepth - 1);

            if (dragDepth === 0) {
                setDragging(false);
            }
        });
        dropzone.addEventListener('drop', (event) => {
            event.preventDefault();
            dragDepth = 0;
            setDragging(false);

            if (!event.dataTransfer?.files?.length) {
                return;
            }

            try {
                input.files = event.dataTransfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            } catch {
                input.click();
            }
        });
    });
}

function initialise() {
    initEmployeeFilters();
    initRecommendations();
    initCompletion();
    initRoleSwitch();
    initUploads();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialise, { once: true });
} else {
    initialise();
}

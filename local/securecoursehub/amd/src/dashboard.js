/**
 * Sends teacher course-request updates without reloading the whole page.
 *
 * @param {string} endpoint The local plugin JSON endpoint.
 */
export const init = (endpoint) => {
    const feedback = document.querySelector('#securecoursehub-ajax-feedback');
    if (!feedback) {
        return;
    }

    const showFeedback = (message, isError) => {
        feedback.textContent = message;
        feedback.className = `alert ${isError ? 'alert-danger' : 'alert-success'}`;
    };

    document.querySelectorAll('.securecoursehub-update-form').forEach((form) => {
        form.addEventListener('submit', async(event) => {
            event.preventDefault();

            const status = form.querySelector('[name="status"]');
            const response = form.querySelector('[name="response"]');
            const submit = form.querySelector('[type="submit"]');
            if (!status || !response || !submit) {
                return;
            }

            submit.disabled = true;
            try {
                const request = await fetch(endpoint, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'update_course_request',
                        id: Number(form.dataset.requestId),
                        courseid: Number(form.dataset.courseId),
                        status: status.value,
                        response: response.value,
                        sesskey: M.cfg.sesskey,
                    }),
                });
                let result;
                try {
                    result = await request.json();
                } catch (error) {
                    if ([400, 401, 403].includes(request.status)) {
                        throw new Error(feedback.dataset.sessionError);
                    }
                    throw new Error(feedback.dataset.networkError);
                }
                if (!request.ok || !result.success) {
                    if ([400, 401, 403].includes(request.status)) {
                        throw new Error(feedback.dataset.sessionError);
                    }
                    throw new Error(result.error || feedback.dataset.networkError);
                }

                const card = document.querySelector(`[data-request-card-id="${result.request.id}"]`);
                if (card) {
                    const statusdisplay = card.querySelector('.securecoursehub-status');
                    const responsedisplay = card.querySelector('.securecoursehub-response');
                    if (statusdisplay) {
                        statusdisplay.textContent = result.request.status;
                    }
                    if (responsedisplay) {
                        responsedisplay.textContent = result.request.response || feedback.dataset.emptyResponse;
                    }
                }
                showFeedback(result.message, false);
            } catch (error) {
                showFeedback(error.message || feedback.dataset.networkError, true);
            } finally {
                submit.disabled = false;
            }
        });
    });
};

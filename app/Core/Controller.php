<?php
namespace App\Core;

/**
 * Base controller. Holds the request and the common render/redirect helpers.
 */
abstract class Controller
{
    protected Request $request;

    public function __construct()
    {
        $this->request = new Request();
    }

    protected function view(string $view, array $data = [], string $layout = 'app'): void
    {
        View::render($view, $data, $layout);
    }

    protected function redirect(string $path): never
    {
        Response::redirect($path);
    }

    protected function back(string $fallback = '/'): never
    {
        Response::back($fallback);
    }

    protected function json(array $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    /**
     * Validate input; on failure flash errors + old input and bounce back.
     * Returns the validated data on success.
     */
    protected function validate(array $rules, array $messages = []): array
    {
        $data      = $this->request->all();
        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            Session::flashErrors($validator->errors());
            Session::flashInput($data);
            Session::flash('error', 'Please correct the highlighted fields.');
            Response::back();
        }

        return $validator->validated();
    }

    /** Write an audit row (use case A15: View System Logs). */
    protected function log(string $action, string $description, ?string $entityType = null, ?int $entityId = null): void
    {
        Logger::activity($action, $description, $entityType, $entityId);
    }

    protected function abort(int $status, string $message = ''): never
    {
        Response::abort($status, $message);
    }

    /** 404 unless the row exists. */
    protected function findOr404(?array $row, string $message = 'Record not found.'): array
    {
        if ($row === null) {
            Response::abort(404, $message);
        }
        return $row;
    }
}

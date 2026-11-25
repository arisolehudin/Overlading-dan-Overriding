<?php
declare(strict_types=1);
/** PHP overloading: __call() menangani method yang TIDAK dideklarasikan. */
final class StudentPresenter {
    public function __call(string $method, array $args): string {
        if ($method !== 'showData') {
            throw new BadMethodCallException("Method tidak dikenal: $method");
        }

        $args = array_values($args); // abaikan named argument

        if (count($args) !== 3) {
            throw new InvalidArgumentException('showData harus menerima tepat 3 argumen: id, name, major.');
        }

        foreach ($args as $i => $arg) {
            if (!is_string($arg) || trim($arg) === '') {
                throw new InvalidArgumentException(
                    'Argumen ke-' . ($i + 1) . ' harus berupa string tidak kosong.'
                );
            }
        }

        [$id, $name, $major] = $args;
        return "NIM: $id, Nama: $name, Prodi: $major";
    }
}

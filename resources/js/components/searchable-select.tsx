import { Check, ChevronsUpDown, Search, X } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type SearchableOption = {
    id: string | number;
    label: string;
    description?: string | null;
};

type Props = {
    id?: string;
    options: SearchableOption[];
    value: string | number;
    onChange: (value: string) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    disabled?: boolean;
    allowEmpty?: boolean;
    emptyLabel?: string;
    className?: string;
};

export function SearchableSelect({
    id,
    options,
    value,
    onChange,
    placeholder = 'Search…',
    searchPlaceholder = 'Type to search…',
    emptyText = 'No matches.',
    disabled = false,
    allowEmpty = false,
    emptyLabel = 'None',
    className,
}: Props) {
    const generatedId = useId();
    const fieldId = id ?? generatedId;
    const listId = `${fieldId}-list`;
    const wrapperRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);

    const selected = options.find(
        (option) => String(option.id) === String(value),
    );

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();
        const matches = term
            ? options.filter((option) => {
                  const haystack = [option.label, option.description ?? '']
                      .join(' ')
                      .toLowerCase();

                  return haystack.includes(term);
              })
            : options;

        return matches.slice(0, 50);
    }, [options, query]);

    useEffect(() => {
        setActiveIndex(0);
    }, [query, open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const handlePointerDown = (event: MouseEvent) => {
            if (
                wrapperRef.current &&
                !wrapperRef.current.contains(event.target as Node)
            ) {
                setOpen(false);
                setQuery('');
            }
        };

        document.addEventListener('mousedown', handlePointerDown);

        return () => document.removeEventListener('mousedown', handlePointerDown);
    }, [open]);

    const selectOption = (nextValue: string) => {
        onChange(nextValue);
        setQuery('');
        setOpen(false);
    };

    const displayValue = open
        ? query
        : (selected?.label ?? (allowEmpty && value === '' ? emptyLabel : ''));

    return (
        <div ref={wrapperRef} className={cn('relative', className)}>
            <Search className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                ref={inputRef}
                id={fieldId}
                value={disabled ? (selected?.label ?? '') : displayValue}
                disabled={disabled}
                placeholder={open ? searchPlaceholder : placeholder}
                autoComplete="off"
                role="combobox"
                aria-expanded={open}
                aria-controls={listId}
                aria-autocomplete="list"
                onFocus={() => {
                    if (disabled) {
                        return;
                    }

                    setOpen(true);
                }}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        setOpen(false);
                        setQuery('');
                        inputRef.current?.blur();
                        return;
                    }

                    if (!open) {
                        if (event.key === 'ArrowDown' || event.key === 'Enter') {
                            event.preventDefault();
                            setOpen(true);
                        }
                        return;
                    }

                    const emptyRow = allowEmpty ? 1 : 0;
                    const lastIndex = filtered.length + emptyRow - 1;

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        setActiveIndex((index) => Math.min(index + 1, lastIndex));
                    }

                    if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        setActiveIndex((index) => Math.max(index - 1, 0));
                    }

                    if (event.key === 'Enter') {
                        event.preventDefault();

                        if (allowEmpty && activeIndex === 0) {
                            selectOption('');
                            return;
                        }

                        const option = filtered[activeIndex - emptyRow];
                        if (option) {
                            selectOption(String(option.id));
                        }
                    }
                }}
                className="pr-16 pl-9"
            />
            <div className="absolute top-1/2 right-1 flex -translate-y-1/2 items-center">
                {allowEmpty && value !== '' && !disabled ? (
                    <button
                        type="button"
                        onClick={() => selectOption('')}
                        className="rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                        aria-label="Clear selection"
                    >
                        <X className="size-4" />
                    </button>
                ) : null}
                <button
                    type="button"
                    disabled={disabled}
                    onClick={() => {
                        if (disabled) {
                            return;
                        }

                        if (open) {
                            setOpen(false);
                            setQuery('');
                            return;
                        }

                        setOpen(true);
                        inputRef.current?.focus();
                    }}
                    className="rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-50"
                    aria-label="Toggle options"
                    tabIndex={-1}
                >
                    <ChevronsUpDown className="size-4" />
                </button>
            </div>

            {open && !disabled ? (
                <div
                    id={listId}
                    role="listbox"
                    className="absolute z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-md border border-border bg-popover shadow-md"
                >
                    {allowEmpty ? (
                        <button
                            type="button"
                            role="option"
                            aria-selected={value === ''}
                            onMouseEnter={() => setActiveIndex(0)}
                            onClick={() => selectOption('')}
                            className={cn(
                                'flex w-full items-center px-3 py-2 text-left text-sm',
                                activeIndex === 0
                                    ? 'bg-muted'
                                    : 'hover:bg-muted/70',
                            )}
                        >
                            {emptyLabel}
                        </button>
                    ) : null}
                    {filtered.length === 0 ? (
                        <p className="px-3 py-4 text-center text-sm text-muted-foreground">
                            {emptyText}
                        </p>
                    ) : (
                        filtered.map((option, index) => {
                            const optionIndex = index + (allowEmpty ? 1 : 0);
                            const isSelected =
                                String(option.id) === String(value);
                            const isActive = optionIndex === activeIndex;

                            return (
                                <button
                                    key={option.id}
                                    type="button"
                                    role="option"
                                    aria-selected={isSelected}
                                    onMouseEnter={() =>
                                        setActiveIndex(optionIndex)
                                    }
                                    onClick={() =>
                                        selectOption(String(option.id))
                                    }
                                    className={cn(
                                        'flex w-full items-center gap-3 px-3 py-2 text-left',
                                        isSelected
                                            ? 'bg-primary/15'
                                            : isActive
                                              ? 'bg-muted'
                                              : 'hover:bg-muted/70',
                                    )}
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">
                                            {option.label}
                                        </p>
                                        {option.description ? (
                                            <p className="truncate text-xs text-muted-foreground">
                                                {option.description}
                                            </p>
                                        ) : null}
                                    </div>
                                    {isSelected ? (
                                        <Check className="size-4 shrink-0 text-primary" />
                                    ) : null}
                                </button>
                            );
                        })
                    )}
                </div>
            ) : null}
        </div>
    );
}

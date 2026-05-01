#!/usr/bin/env sh
set -e

REPO="k-antwi/fly-cli"
BINARY_NAME="fly"
INSTALL_DIR="${HOME}/.fly"
BINARY_PATH="${INSTALL_DIR}/${BINARY_NAME}"
DOWNLOAD_URL="https://github.com/${REPO}/releases/latest/download/${BINARY_NAME}"

# ── helpers ────────────────────────────────────────────────────────────────────

print_info()    { printf '\033[0;34m  → %s\033[0m\n' "$1"; }
print_success() { printf '\033[0;32m  ✓ %s\033[0m\n' "$1"; }
print_error()   { printf '\033[0;31m  ✗ %s\033[0m\n' "$1" >&2; }

# ── preflight ──────────────────────────────────────────────────────────────────

if ! command -v curl >/dev/null 2>&1; then
    print_error "curl is required but was not found. Please install curl and try again."
    exit 1
fi

# ── create install directory ───────────────────────────────────────────────────

if [ ! -d "$INSTALL_DIR" ]; then
    print_info "Creating ${INSTALL_DIR}"
    mkdir -p "$INSTALL_DIR"
fi

# ── download binary ────────────────────────────────────────────────────────────

print_info "Downloading latest fly binary..."

if ! curl -fsSL "$DOWNLOAD_URL" -o "$BINARY_PATH"; then
    print_error "Download failed. Check your internet connection or visit https://github.com/${REPO}/releases."
    exit 1
fi

chmod +x "$BINARY_PATH"
print_success "Downloaded fly to ${BINARY_PATH}"

# ── update shell profile ───────────────────────────────────────────────────────

PATH_SNIPPET='export PATH="$HOME/.fly:$PATH"'

add_to_profile() {
    profile_file="$1"

    # Do nothing if the line is already there
    if grep -qF '.fly' "$profile_file" 2>/dev/null; then
        print_success "PATH already configured in ${profile_file}"
        return
    fi

    printf '\n# fly CLI\n%s\n' "$PATH_SNIPPET" >> "$profile_file"
    print_success "Added .fly to PATH in ${profile_file}"
}

# Detect the user's shell and pick the right profile
case "${SHELL}" in
    */zsh)
        add_to_profile "${HOME}/.zshrc"
        PROFILE_FILE="${HOME}/.zshrc"
        ;;
    */bash)
        if [ -f "${HOME}/.bashrc" ]; then
            add_to_profile "${HOME}/.bashrc"
            PROFILE_FILE="${HOME}/.bashrc"
        else
            add_to_profile "${HOME}/.bash_profile"
            PROFILE_FILE="${HOME}/.bash_profile"
        fi
        ;;
    *)
        # Unknown shell — try common files in order
        for f in "${HOME}/.zshrc" "${HOME}/.bashrc" "${HOME}/.bash_profile" "${HOME}/.profile"; do
            if [ -f "$f" ]; then
                add_to_profile "$f"
                PROFILE_FILE="$f"
                break
            fi
        done
        ;;
esac

# ── done ───────────────────────────────────────────────────────────────────────

printf '\n'
print_success "fly installed successfully!"
printf '\n'
printf '  Reload your shell or run:\n'
printf '\n'
printf '    source %s\n' "${PROFILE_FILE:-~/.zshrc}"
printf '\n'
printf '  Then verify with:\n'
printf '\n'
printf '    fly --version\n'
printf '\n'

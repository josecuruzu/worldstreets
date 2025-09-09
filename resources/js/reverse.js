document.addEventListener('alpine:init', () => {
            Alpine.data('addressFinderApp', () => ({
                searchQuery: '',
                isLoading: false,
                showResults: false,
                searchResults: null,
                usAddresses: [
                    "1600 Pennsylvania Avenue NW, Washington, D.C.",
                    "2131 South 1st Street, San Jose, CA",
                    "1550 N Broadway St, San Francisco, CA",
                    "1776 Independence Avenue, Philadelphia, PA",
                    "3162 W 111th Street, Chicago, IL",
                    "100 N Alafaya Trail, Orlando, FL",
                    "901 North 15th Street, Omaha, NE",
                    "350 5th Avenue, New York, NY"
                ],
                get filteredSuggestions() {
                    const query = this.searchQuery.toLowerCase().trim();
                    if (query.length < 3) return [];
                    return this.usAddresses.filter(addr => addr.toLowerCase().includes(query));
                },
                get showSuggestions() {
                    return this.searchQuery.length > 2 && this.filteredSuggestions.length > 0;
                },
                search() {
                    const query = this.searchQuery.trim();
                    if (query === '') return;
                    this.isLoading = true;
                    this.showResults = true;
                    this.searchResults = null;
                    setTimeout(() => {
                        const foundAddress = this.usAddresses.find(addr => addr.toLowerCase().includes(query.toLowerCase()));
                        if (foundAddress) {
                            this.searchResults = { found: true, address: foundAddress };
                        } else {
                            this.searchResults = { found: false };
                        }
                        this.isLoading = false;
                    }, 1500);
                },
                selectSuggestion(address) {
                    this.searchQuery = address;
                    this.search();
                }
            }));
        });
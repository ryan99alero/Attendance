@if(isset($error))
    <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
        <p class="text-red-800 font-medium">Error calculating holiday dates:</p>
        <p class="text-red-600 text-sm mt-1">{{ $error }}</p>
    </div>
@else
    <div class="space-y-2">
        @foreach($dates as $date)
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span class="font-medium text-gray-900">{{ $date }}</span>
            </div>
        @endforeach
    </div>
@endif
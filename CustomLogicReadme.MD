### **Formating InlineEditing DateTime Display**

For the Inline editing function to work. It requires using Carbon. Reason being TextColumn allows hiding seconds but TextInputColumn doesn't. So we have to add additional code to the App Model for Attendance.

1. Add into Resource file.
   2. use Filament\Tables\Columns\TextInputColumn;
   3. In Form Section.
      4.             DateTimePicker::make('punch_time')
                ->label('Punch Time')
                ->seconds(false)
                ->displayFormat('Y-m-d H:i')
                ->required(),
   5. In Table Section.
      6.        TextInputColumn::make('punch_time')
                ->label('Punch Time')
                ->rules(['required', 'date_format:Y-m-d H:i']) // Validation rule for datetime
                ->placeholder('YYYY-MM-DD HH:MM')
                ->searchable(),

In any App dModel add use line towards top.

1.  use Carbon\Carbon;
   2.   Next still in App Model edit the Protected Casts.
      3. From:
      3. protected $casts = [
      'punch_time' => 'datetime',
      4. To:
      4. protected $casts = [
        'punch_time' => 'datetime:Y-m-d\TH:i', // ISO 8601 format
   3. Next We need to add 2 additional functions.
      4. public function getPunchTimeAttribute($value)
      4.{
      4.return Carbon::parse($value)->format('Y-m-d H:i');
      4.}
      5. public function setPunchTimeAttribute($value)
      5. {
      5. $this->attributes['punch_time'] = Carbon::parse($value)->format('Y-m-d H:i:s');
      5. }

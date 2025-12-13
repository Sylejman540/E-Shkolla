<div id="addSchoolForm" class="hidden mt-8">
  <div class="flex justify-center">
    <div class="w-full max-w-4xl">

      <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        
        <div class="mb-8">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
            Add a new school
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Enter the basic information about the school.
          </p>
        </div>

      <div class="grid grid-cols-1 gap-x-8 gap-y-10 border-b border-gray-900/10 pb-8 md:grid-cols-3 dark:border-white/10">

        <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 md:col-span-2">
         <div class="sm:col-span-3">
          <label for="first-name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">School Name</label>
          <div class="mt-2">
            <input id="first-name" type="text" name="first-name" autocomplete="given-name" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
          </div>
         </div>

         <div class="sm:col-span-3">
          <label for="last-name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">School Admin</label>
          <div class="mt-2">
            <input id="last-name" type="text" name="last-name" autocomplete="family-name" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
          </div>
         </div>

         <div class="sm:col-span-4">
          <label for="email" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Email address</label>
          <div class="mt-2">
            <input id="email" type="email" name="email" autocomplete="email" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
          </div>
         </div>

         <div class="sm:col-span-3">
          <label for="city" class="block text-sm/6 font-medium text-gray-900 dark:text-white">City</label>
          <div class="mt-2">
            <input id="city" type="text" name="city" autocomplete="city" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
          </div>
         </div>

         <div class="sm:col-span-2">
          <label for="status" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Status</label>
          <div class="mt-2">
            <input id="status" type="text" name="status" autocomplete="status" class="border border-1 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500" />
          </div>
         </div>
        </div>
      </div>

      <div class="mt-6 flex justify-end gap-x-4">
        <button type="button" id="cancel" class="text-sm font-semibold text-gray-700 hover:text-gray-900 dark:text-gray-300">Cancel</button>

        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500">Save</button>
      </div>

    </div>
  </div>
</div>
